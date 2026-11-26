<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\LanguageStatus;
use App\Models\Item;
use App\Models\ItemTranslation;
use App\Models\TerminologyTerm;
use App\Services\PilotEvidence;
use App\Services\TerminologyImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Domain B, batch 1, all locales, single import file v1: the expected result
 * of each step, on top of a staging database that already holds the Ethics
 * Pilot (24 items, 50 terminology records).
 */
class DomainBImportTest extends TestCase
{
    private const CODES = ['B-01-001', 'B-01-002', 'B-01-003', 'B-02-001', 'B-02-002', 'B-03-001', 'B-03-002', 'B-03-003'];

    private const LOCALES = ['pt-BR', 'es-419', 'uk-UA', 'ru-UA'];

    /** Staging before Domain B: taxonomy, the deploy dictionary, the A to E bank and every Ethics step. */
    private function seedStagingBeforeDomainB(): void
    {
        $this->seedTaxonomy();
        app(TerminologyImporter::class)->import(array_map(
            fn ($f) => $this->readJson($f),
            glob(config('examprep.terminology.path').'/*.json'),
        ));
        $this->importFile($this->readJson(config('examprep.items_file')));
        foreach ([3, 4, 5, 6] as $step) {
            $this->importFile($this->pilotStep($step));
        }
    }

    private function step(int $step): array
    {
        return $this->pilotStep($step, 'domain-b');
    }

    private function runStep(int $step): void
    {
        $this->artisan('pilot:import', ['--batch' => 'domain-b', '--step' => $step])->assertSuccessful();
    }

    public function test_step_1_terminology_adds_110_records_to_the_50_already_there(): void
    {
        $this->seedStagingBeforeDomainB();
        $this->assertSame(50, TerminologyTerm::count());
        $ethicsKeys = TerminologyTerm::distinct()->pluck('term_key')->all();

        $this->runStep(1);

        $this->assertSame(160, TerminologyTerm::count());
        $this->assertSame(32, TerminologyTerm::distinct()->count('term_key'));
        foreach (['en-US', 'pt-BR', 'es-419', 'uk-UA', 'ru-UA'] as $locale) {
            $this->assertSame(32, TerminologyTerm::where('locale', $locale)->count(), $locale);
        }
        $new = TerminologyTerm::whereNotIn('term_key', $ethicsKeys)->get();
        $this->assertCount(110, $new);
        $this->assertSame(22, $new->where('status', 'canonical')->count());
        $this->assertSame(88, $new->where('status', 'approved')->count());
        $this->assertSame(44, $new->where('reviewer', 'Americo Silva')->count());
        $this->assertSame(44, $new->where('reviewer', 'Yevheniia Lobas')->count());
        $this->assertSame(0, app(PilotEvidence::class)->collect('B')['counts']['terminology_duplicates']);
        $this->assertSame('avaliação de preferências', TerminologyTerm::lookup('preference_assessment', 'pt-BR'));

        $this->runStep(1); // re-run: nothing new
        $this->assertSame(160, TerminologyTerm::count());
    }

    public function test_step_2_eight_english_masters(): void
    {
        $this->seedStagingBeforeDomainB();
        $this->runStep(1);
        $this->assertSame(24, Item::count());

        $this->runStep(2);

        $this->assertSame(32, Item::count());
        $items = Item::whereIn('code', self::CODES)->with('task', 'translations')->get()->keyBy('code');
        $this->assertCount(8, $items);
        foreach ($items as $code => $item) {
            $this->assertSame(1, $item->master_version, $code);
            $this->assertSame(ItemStatus::Draft, $item->status, $code);
            $this->assertSame('pending', $item->ip_clearance, $code);
            $this->assertFalse($item->source_review_hold, $code);
            $this->assertSame(['en-US'], $item->translations->pluck('locale')->all(), $code);
            $this->assertCount(4, $item->translations->first()->options);
            $this->assertStringContainsString('LOCKED 2026-11-14, fingerprint', $item->translations->first()->language_notes);
        }
        $this->assertSame(['A' => 2, 'B' => 2, 'C' => 2, 'D' => 2], $items->countBy(fn ($i) => $i->getAttribute('key'))->sortKeys()->all());
        $this->assertSame(['B.1' => 3, 'B.2' => 2, 'B.3' => 3], $items->countBy(fn ($i) => $i->task->code)->sortKeys()->all());
        $this->assertStringContainsString('v3.1 LOCKED', $items['B-03-002']->translations->first()->language_notes);

        $b02 = $items['B-02-002'];
        $this->assertSame('D', $b02->getAttribute('key'));
        $this->assertSame('A', $b02->target_misconception);
        $this->assertSame('1.03; 1.06; 2.02; 2.06', $b02->source_ref);
        $this->assertSame('Cooper 3rd ed. (2020); RBT Ethics Code (2.0), updated 08/2024', $b02->authority_version);
        $this->assertNull($items['B-01-001']->source_ref, 'the literal "None" in the sheet is stored as empty');

        // The prototype items B-01 and B-02 are untouched.
        $this->assertSame(1, Item::where('code', 'B-01')->value('version'));
    }

    public function test_step_3_thirty_two_approved_language_records(): void
    {
        $this->seedStagingBeforeDomainB();
        $this->runStep(1);
        $this->runStep(2);

        $this->runStep(3);

        $records = ItemTranslation::whereHas('item', fn ($q) => $q->whereIn('code', self::CODES))
            ->where('locale', '!=', 'en-US')->get();
        $this->assertCount(32, $records);
        $this->assertSame(32, $records->where('language_status', LanguageStatus::Approved)->count());
        $this->assertSame(0, ItemTranslation::where('language_status', 'stale')->whereIn('item_id', Item::whereIn('code', self::CODES)->pluck('id'))->count());
        foreach (['pt-BR' => ['Americo Silva', '2026-11-19'], 'es-419' => ['Americo Silva', '2026-11-19'],
            'uk-UA' => ['Yevheniia Lobas', '2026-11-20'], 'ru-UA' => ['Yevheniia Lobas', '2026-11-20']] as $locale => [$who, $when]) {
            $mine = $records->where('locale', $locale);
            $this->assertCount(8, $mine, $locale);
            foreach ($mine as $t) {
                $this->assertSame([1, 1, $who, $when], [$t->version, $t->source_master_version, $t->language_reviewer, $t->language_reviewed_on->toDateString()]);
            }
        }

        // The evidence for this batch lists only its eight items.
        $evidence = app(PilotEvidence::class)->collect('B', PilotEvidence::batchCodes('domain-b'));
        $this->assertCount(8, $evidence['masters']);
        $this->assertSame([8, 8, 8, 8], array_column($evidence['languages'], 'approved'));
        $this->assertSame([0, 0, 0, 0], array_column($evidence['languages'], 'stale'));
        $this->assertSame(['A' => 2, 'B' => 2, 'C' => 2, 'D' => 2], $evidence['keys']);
    }

    public function test_items_stay_draft_and_are_not_shown_to_candidates(): void
    {
        $this->seedStagingBeforeDomainB();
        foreach ([1, 2, 3] as $step) {
            $this->runStep($step);
        }

        $this->assertSame(0, Item::whereIn('code', self::CODES)->where('status', '!=', 'draft')->count());
        $this->assertSame(0, Item::availableTo(false)->whereIn('code', self::CODES)->count());
    }

    public function test_master_owned_fields_come_from_the_english_master(): void
    {
        $this->seedStagingBeforeDomainB();
        foreach ([1, 2, 3] as $step) {
            $this->runStep($step);
        }

        foreach (Item::whereIn('code', self::CODES)->with('translations')->get() as $item) {
            $key = $item->getAttribute('key');
            foreach (self::LOCALES as $locale) {
                $row = collect($this->step(3)['languages'])->where('item_code', $item->code)->firstWhere('locale', $locale);
                $t = $item->currentTranslations()->get($locale);
                $this->assertSame($row['options'][$key]['rationale'], $t->rationale, "{$item->code} {$locale}");
                $this->assertArrayNotHasKey($key, $t->option_rationales);
            }
        }
    }

    public function test_rerunning_any_step_creates_no_duplicates(): void
    {
        $this->seedStagingBeforeDomainB();
        foreach ([1, 2, 3] as $step) {
            $this->runStep($step);
        }
        $before = [TerminologyTerm::count(), Item::count(), ItemTranslation::count(), Item::orderBy('id')->pluck('version', 'code')->all()];

        foreach ([3, 2, 1, 2, 3] as $step) {
            $this->runStep($step);
        }

        $this->assertSame($before, [TerminologyTerm::count(), Item::count(), ItemTranslation::count(), Item::orderBy('id')->pluck('version', 'code')->all()]);
        $this->assertSame(0, app(PilotEvidence::class)->collect('B')['counts']['language_record_duplicates']);
    }

    public function test_language_records_need_their_masters_first(): void
    {
        $this->seedStagingBeforeDomainB();
        $this->runStep(1);

        $this->artisan('pilot:import', ['--batch' => 'domain-b', '--step' => 3])
            ->expectsOutputToContain('Import rejected')->assertFailed();
        $this->assertSame(0, Item::whereIn('code', self::CODES)->count());
    }

    public function test_a_later_text_change_raises_master_version_and_turns_four_records_stale(): void
    {
        $this->seedStagingBeforeDomainB();
        foreach ([1, 2, 3] as $step) {
            $this->runStep($step);
        }
        $file = $this->step(2);
        $file['masters'][0]['stem'] .= ' (edited)';

        try {
            $this->importFile($file);
            $this->fail('A text change without a higher master_version must be refused.');
        } catch (ValidationException) {
        }

        $file['masters'][0]['master_version'] = 2;
        $report = $this->importFile($file);

        $this->assertCount(4, $report['stale']);
        $this->assertSame(4, Item::where('code', 'B-01-001')->first()->translations()->where('language_status', 'stale')->count());
    }

    public function test_commands(): void
    {
        $this->seedStagingBeforeDomainB();

        $this->artisan('pilot:import', ['--batch' => 'domain-c', '--step' => 1])->assertFailed();
        $this->artisan('pilot:import', ['--batch' => 'domain-b', '--step' => 4])->assertFailed();
        $this->artisan('pilot:import', ['--batch' => 'domain-b', '--step' => 2, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame(0, Item::whereIn('code', self::CODES)->count());

        $this->artisan('pilot:import', ['--batch' => 'domain-b', '--step' => 1])
            ->expectsOutputToContain('Phase 1, step 1')->assertSuccessful();
        $this->artisan('pilot:evidence', ['--batch' => 'domain-b'])->expectsOutputToContain('batch domain-b')->assertSuccessful();
        $this->artisan('pilot:evidence', ['--batch' => 'nope'])->assertFailed();
    }
}
