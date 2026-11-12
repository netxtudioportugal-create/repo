<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\LanguageStatus;
use App\Enums\StaffRole;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemTranslation;
use App\Models\Learner;
use App\Models\Task;
use App\Models\TerminologyTerm;
use App\Models\User;
use App\Services\LanguageReview;
use App\Services\PilotEvidence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Ethics Pilot, all locales, single import file v2: the expected result of
 * each phase and step (Checklist v1.7), imported one step at a time.
 */
class EthicsPilotImportTest extends TestCase
{
    private const LOCALES = ['pt-BR', 'es-419', 'uk-UA', 'ru-UA'];

    private const BUMPED_IN_STEP_3 = ['F-02-001', 'F-03-001', 'F-07-001'];

    private const BUMPED_IN_STEP_5 = ['F-02-001', 'F-05-002', 'F-10-001'];

    private function runSteps(int ...$steps): array
    {
        $reports = [];
        foreach ($steps as $step) {
            $reports[$step] = $this->importFile($this->pilotStep($step));
        }

        return $reports;
    }

    /** language_status of the current record of every item, for one locale: [item_code => status]. */
    private function current(string $locale): array
    {
        return Item::with('translations')->whereHas('domain', fn ($q) => $q->where('code', 'F'))->get()
            ->mapWithKeys(fn (Item $i) => [$i->code => $i->currentTranslations()->get($locale)?->setRelation('item', $i)])
            ->filter()->all();
    }

    private function statuses(string $locale): array
    {
        return array_map(fn (ItemTranslation $t) => $t->language_status->value, $this->current($locale));
    }

    private function masterVersions(): array
    {
        return Item::whereHas('domain', fn ($q) => $q->where('code', 'F'))->orderBy('code')->pluck('master_version', 'code')->all();
    }

    /**
     * The state before step 3: 14 masters at master_version 1 and 56 language
     * records made from them. The three items that step 3 raises carry an
     * earlier wording.
     */
    private function seedLegacy(): void
    {
        $masters = $this->pilotStep(3);
        $languages = $this->pilotStep(4);
        unset($masters['step'], $languages['step']);

        foreach ($masters['masters'] as &$m) {
            if (in_array($m['item_code'], self::BUMPED_IN_STEP_3, true)) {
                $m['stem'] = 'Earlier wording. '.$m['stem'];
            }
            $m['master_version'] = 1;
        }
        foreach ($languages['languages'] as &$l) {
            $l['source_master_version'] = 1;
        }

        $this->importFile($masters);
        $this->importFile($languages);
    }

    public function test_step_1_locale_migration_renames_in_place_and_keeps_every_row(): void
    {
        $this->seedBank();
        $item = Item::where('code', 'A-01')->first();
        $back = ['en-US' => 'en', 'pt-BR' => 'pt', 'es-419' => 'es-LATAM', 'uk-UA' => 'uk', 'ru-UA' => 'ru-RU'];
        foreach ($back as $new => $old) {
            ItemTranslation::where('item_id', $item->id)->where('locale', $new)->update(['locale' => $old]);
        }
        Domain::where('code', 'A')->update(['names' => ['en' => 'Data', 'pt' => 'Dados', 'ru-RU' => 'Данные']]);
        $learner = Learner::create(['locale' => 'ru-RU']);
        $before = [Item::count(), ItemTranslation::count(), Learner::count()];
        $ids = Item::orderBy('id')->pluck('code', 'id')->all();

        (require database_path('migrations/2026_11_12_000100_migrate_locale_codes.php'))->up();

        $this->assertSame($before, [Item::count(), ItemTranslation::count(), Learner::count()]);
        $this->assertSame($ids, Item::orderBy('id')->pluck('code', 'id')->all());
        $this->assertSame('ru-UA', $learner->fresh()->locale);
        $this->assertSame(['en-US' => 'Data', 'pt-BR' => 'Dados', 'ru-UA' => 'Данные'], Domain::where('code', 'A')->first()->names);
        $evidence = app(PilotEvidence::class)->collect();
        $this->assertSame(['en-US', 'es-419', 'pt-BR', 'ru-UA', 'uk-UA'], $evidence['locales']['in_use']);
        $this->assertSame([], $evidence['locales']['unexpected']);
    }

    public function test_step_2_tasks_and_terminology(): void
    {
        $this->seedTaxonomy();
        $this->artisan('terms:import')->assertSuccessful();

        $this->assertSame(43, Task::count());
        $this->assertSame(0, Task::whereNotNull('official_wording')->count());
        $this->assertSame(43, Task::where('source_version', 'Updated 09/2024')->whereNotNull('source_url')->count());
        $this->assertSame('Continuous measurement', Task::where('code', 'A.1')->first()->label('en-US'));
        $this->assertSame(
            ['A' => 8, 'B' => 3, 'C' => 11, 'D' => 7, 'E' => 4, 'F' => 10],
            Task::join('domains', 'domains.id', '=', 'tasks.domain_id')->selectRaw('domains.code, count(*) as n')
                ->groupBy('domains.code')->orderBy('domains.code')->pluck('n', 'code')->map(fn ($n) => (int) $n)->all(),
        );
        $this->assertSame(50, TerminologyTerm::count());
        $this->assertSame(0, app(PilotEvidence::class)->collect()['counts']['terminology_duplicates']);

        $this->seedTaxonomy(); // safe to run on every deploy
        $this->assertSame(43, Task::count());
    }

    public function test_step_3_english_masters_turn_older_language_records_stale(): void
    {
        $this->seedTaxonomy();
        $this->seedLegacy();
        $before = array_map(fn ($l) => $this->statuses($l), array_combine(self::LOCALES, self::LOCALES));

        $report = $this->runSteps(3)[3];

        $versions = $this->masterVersions();
        $this->assertCount(14, $versions);
        foreach ($versions as $code => $v) {
            $this->assertSame(in_array($code, self::BUMPED_IN_STEP_3, true) ? 2 : 1, $v, $code);
        }
        $this->assertSame(14, Item::where('status', ItemStatus::Draft->value)->count());
        $this->assertSame(56, DB::table('item_translations')->where('locale', 'en-US')->count() * 4);

        $this->assertCount(12, $report['stale']);
        foreach (self::LOCALES as $locale) {
            $now = $this->statuses($locale);
            $this->assertSame(3, count(array_keys($now, 'stale')), "{$locale} stale count");
            foreach ($now as $code => $status) {
                $this->assertSame(in_array($code, self::BUMPED_IN_STEP_3, true) ? 'stale' : $before[$locale][$code], $status, "{$code} {$locale}");
            }
        }
    }

    public function test_step_3_on_a_new_database_creates_fourteen_masters_with_fifty_six_options(): void
    {
        $this->seedTaxonomy();

        $report = $this->runSteps(3)[3];

        $this->assertCount(14, $report['masters']['created']);
        $this->assertSame(14, ItemTranslation::where('locale', 'en-US')->get()->sum(fn ($t) => count($t->options)) / 4);
        $item = Item::where('code', 'F-05-001-R')->first();
        $this->assertSame('A', $item->getAttribute('key'));
        $this->assertSame('C', $item->target_misconception);
        $this->assertTrue($item->is_rule_dependent);
        $this->assertSame(12, $item->cadence_months);
        $this->assertSame('pending', $item->ip_clearance);
        $this->assertSame('F.5', $item->task->code);
        $this->assertSame('2026-10-03', $item->source_checked_on->toDateString());
        $this->assertCount(3, $item->translations->first()->option_rationales);
    }

    public function test_step_4_language_records(): void
    {
        $this->seedTaxonomy();
        $report = $this->runSteps(3, 4)[4];

        $this->assertCount(56, $report['languages']['created']);
        $this->assertSame(0, ItemTranslation::where('language_status', 'stale')->count());

        foreach (self::LOCALES as $locale) {
            $records = $this->current($locale);
            $this->assertCount(14, $records);
            foreach ($records as $code => $t) {
                $expected = match (true) {
                    in_array($code, ['F-02-001', 'F-07-001'], true) => 'draft',
                    $code === 'F-03-001' => 'in_language_review',
                    $locale === 'uk-UA' => 'approved',
                    $locale === 'ru-UA' => 'in_language_review',
                    default => 'draft',
                };
                $this->assertSame($expected, $t->language_status->value, "{$code} {$locale}");
                $this->assertSame($t->item->master_version, $t->source_master_version, "{$code} {$locale}");
                if ($locale === 'uk-UA' && $expected === 'approved') {
                    $this->assertSame(['Yevheniia Lobas', '2026-10-09'], [$t->language_reviewer, $t->language_reviewed_on->toDateString()]);
                }
            }
        }
    }

    public function test_step_5_version_test_turns_twelve_records_stale_on_their_own(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4);
        $before = array_map(fn ($l) => $this->statuses($l), array_combine(self::LOCALES, self::LOCALES));

        $report = $this->runSteps(5)[5];

        $expected = ['F-02-001' => 3, 'F-03-001' => 2, 'F-05-002' => 2, 'F-07-001' => 2, 'F-10-001' => 2];
        foreach ($this->masterVersions() as $code => $v) {
            $this->assertSame($expected[$code] ?? 1, $v, $code);
        }

        $this->assertCount(12, $report['stale']);
        $this->assertSame(12, ItemTranslation::where('language_status', 'stale')->count());
        foreach (self::LOCALES as $locale) {
            foreach ($this->statuses($locale) as $code => $status) {
                $this->assertSame(in_array($code, self::BUMPED_IN_STEP_5, true) ? 'stale' : $before[$locale][$code], $status, "{$code} {$locale}");
            }
        }
    }

    public function test_step_6_updated_and_reviewed_language_records(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);

        foreach (['pt-BR', 'es-419'] as $locale) {
            $records = $this->current($locale);
            $this->assertCount(14, $records);
            foreach ($records as $code => $t) {
                $this->assertSame([1, 'draft'], [$t->version, $t->language_status->value], "{$code} {$locale}");
                $this->assertSame($t->item->master_version, $t->source_master_version);
            }
            $this->assertSame(14, ItemTranslation::where('locale', $locale)->count(), 'updated in place, no new rows');
        }

        foreach (['uk-UA' => '2026-11-07', 'ru-UA' => '2026-11-05'] as $locale => $date) {
            $records = $this->current($locale);
            $this->assertCount(14, $records);
            foreach ($records as $code => $t) {
                $this->assertSame(
                    [2, 'approved', 'Yevheniia Lobas', $date],
                    [$t->version, $t->language_status->value, $t->language_reviewer, $t->language_reviewed_on->toDateString()],
                    "{$code} {$locale}",
                );
                $this->assertSame($t->item->master_version, $t->source_master_version);
            }

            // Version 1 rows stay as history; those of the three changed masters stay stale.
            $history = ItemTranslation::where('locale', $locale)->where('version', 1)->with('item')->get();
            $this->assertCount(14, $history);
            $this->assertEqualsCanonicalizing(self::BUMPED_IN_STEP_5, $history->filter->isStale()->map(fn ($t) => $t->item->code)->all());
        }

        // No current record is stale, and candidates get the reviewed text.
        $evidence = app(PilotEvidence::class)->collect();
        $this->assertSame([0, 0, 0, 0], array_column($evidence['languages'], 'stale'));
        $this->assertSame([14, 14, 14, 14], array_column($evidence['languages'], 'current'));
        [$content, $fallback] = Item::where('code', 'F-02-001')->first()->contentFor('uk-UA');
        $this->assertFalse($fallback);
        $this->assertSame(2, $content->version);
    }

    public function test_phase_2_reviewed_options_have_no_final_period_and_no_long_dashes(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);

        foreach (ItemTranslation::whereIn('locale', ['uk-UA', 'ru-UA'])->where('version', 2)->get() as $t) {
            foreach ($t->options as $text) {
                $this->assertStringEndsNotWith('.', $text);
            }
            $this->assertStringNotContainsString('—', $t->stem.$t->rationale.implode('', $t->options).implode('', $t->option_rationales ?? []));
        }
    }

    public function test_rerunning_any_step_creates_no_duplicates_and_undoes_nothing(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);
        $snapshot = fn () => [
            ItemTranslation::orderBy('id')->get(['id', 'locale', 'version', 'source_master_version', 'language_status', 'stem'])->toArray(),
            $this->masterVersions(),
            Item::orderBy('id')->pluck('version', 'code')->all(),
        ];
        $before = $snapshot();
        $this->assertCount(14 + 56 + 28, $before[0]);

        foreach ([3, 4, 5, 6, 4, 3, 6, 5] as $step) {
            $report = $this->runSteps($step)[$step];
            $this->assertSame([], $report['masters']['created']);
            $this->assertSame([], $report['languages']['created']);
            $this->assertSame([], $report['stale']);
        }

        $this->assertSame($before, $snapshot());
        $this->assertSame(0, app(PilotEvidence::class)->collect()['counts']['language_record_duplicates']);
    }

    public function test_a_reimport_never_overwrites_a_status_set_by_a_reviewer(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);
        $reviewer = User::factory()->create(['role' => StaffRole::LanguageReviewer, 'name' => 'Americo Silva']);
        $record = $this->current('pt-BR')['F-01-001'];

        app(LanguageReview::class)->decide($record, $reviewer, LanguageStatus::Approved);
        $this->runSteps(6);

        $record->refresh();
        $this->assertSame(LanguageStatus::Approved, $record->language_status);
        $this->assertSame('Americo Silva', $record->language_reviewer);
        $this->assertSame($reviewer->id, $record->status_set_by);
    }

    public function test_language_review_needs_the_role_the_current_version_and_a_current_master(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5);
        $review = app(LanguageReview::class);
        $reviewer = User::factory()->create(['role' => StaffRole::LanguageReviewer]);
        $fresh = $this->current('pt-BR')['F-01-001'];
        $stale = $this->current('pt-BR')['F-02-001'];

        try {
            $review->decide($fresh, User::factory()->create(['role' => StaffRole::Tester]), LanguageStatus::Approved);
            $this->fail('A tester cannot approve a language record.');
        } catch (AuthorizationException) {
        }

        foreach ([[$stale, LanguageStatus::Approved], [$fresh, LanguageStatus::Stale]] as [$record, $to]) {
            try {
                $review->decide($record, $reviewer, $to);
                $this->fail('Expected the decision to be refused.');
            } catch (ValidationException) {
            }
        }

        $this->actingAs($reviewer)->post(route('staff.items.language', 'F-01-001'), [
            'record' => $fresh->id, 'language_status' => 'in_language_review', 'notes' => 'Started.',
        ])->assertRedirect(route('staff.items.show', 'F-01-001'));
        $this->assertSame(LanguageStatus::InLanguageReview, $fresh->fresh()->language_status);
        $this->actingAs($reviewer)->get(route('staff.items.show', 'F-02-001'))->assertOk()->assertSee('Stale (master changed)');
        $this->actingAs($reviewer)->get(route('staff.items.index'))->assertOk();
    }

    public function test_an_approved_text_is_never_changed_in_place(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);
        $file = $this->pilotStep(6);
        $i = array_search('uk-UA', array_column($file['languages'], 'locale'), true);
        $file['languages'][$i]['stem'] .= ' (edited)';

        try {
            $this->importFile($file);
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('new version', implode(' ', array_merge(...array_values($e->errors()))));
        }
    }

    public function test_language_rows_are_refused_without_their_master_or_with_stale_status(): void
    {
        $this->seedTaxonomy();

        try {
            $this->runSteps(4);
            $this->fail('Language records need their masters first.');
        } catch (ValidationException $e) {
            $this->assertSame(0, ItemTranslation::count());
        }

        $this->runSteps(3);
        $file = $this->pilotStep(4);
        $file['languages'][0]['language_status'] = 'stale';
        $this->expectException(ValidationException::class);
        $this->importFile($file);
    }

    public function test_master_owned_fields_come_from_the_english_master(): void
    {
        $this->seedTaxonomy();
        $this->runSteps(3, 4, 5, 6);

        foreach (['F-02-001', 'F-05-002', 'F-10-001'] as $code) {
            $item = Item::where('code', $code)->first();
            $master = collect($this->pilotStep(5)['masters'])->firstWhere('item_code', $code);
            $key = collect($master['options'])->filter(fn ($o) => $o['is_key'])->keys()->first();
            $this->assertSame($key, $item->getAttribute('key'));

            foreach (self::LOCALES as $locale) {
                $row = collect($this->pilotStep(6)['languages'])->where('item_code', $code)->firstWhere('locale', $locale);
                $t = $item->currentTranslations()->get($locale);
                $this->assertSame($row['options'][$key]['rationale'], $t->rationale, "{$code} {$locale}");
                $this->assertArrayNotHasKey($key, $t->option_rationales);
            }
        }
    }

    public function test_commands_run_a_step_and_print_the_evidence(): void
    {
        $this->seedTaxonomy();

        $this->artisan('pilot:import')->assertFailed();
        $this->artisan('pilot:import', ['--step' => 3, '--dry-run' => true])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame(0, Item::count());

        foreach ([3, 4, 5, 6] as $step) {
            $this->artisan('pilot:import', ['--step' => $step])->assertSuccessful();
        }
        $this->artisan('pilot:evidence')->expectsOutputToContain('No locale outside')->assertSuccessful();
        $this->artisan('pilot:evidence', ['--json' => true])->assertSuccessful();
        $this->assertSame(3, Item::where('code', 'F-02-001')->value('master_version'));
    }
}
