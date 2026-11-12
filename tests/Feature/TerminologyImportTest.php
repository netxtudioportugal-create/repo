<?php

namespace Tests\Feature;

use App\Models\TerminologyTerm;
use App\Services\TerminologyImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mirrors the validation checklists of the four import packages v2 (en-US, pt-BR, es-419, uk-UA + ru-UA). */
class TerminologyImportTest extends TestCase
{
    private const EXPECTED = [
        'extinction' => ['en-US' => 'Extinction', 'pt-BR' => 'Extinção', 'es-419' => 'Extinción', 'uk-UA' => 'Згасання', 'ru-UA' => 'Гашение'],
        'extinction_burst' => ['en-US' => 'Extinction burst', 'pt-BR' => 'Surto de extinção', 'es-419' => 'Estallido de extinción', 'uk-UA' => 'Сплеск згасання', 'ru-UA' => 'Угасательное обострение'],
        'escape_extinction' => ['en-US' => 'Escape extinction', 'pt-BR' => 'Extinção de fuga', 'es-419' => 'Extinción del escape', 'uk-UA' => 'Згасання поведінки, підтримуваної втечею від вимог або ситуації', 'ru-UA' => 'Гашение избегающего поведения'],
        'prompt_fading' => ['en-US' => 'Prompt fading', 'pt-BR' => 'Esvanecimento de prompts (fading)', 'es-419' => 'Desvanecimiento de ayudas o prompts (fading)', 'uk-UA' => 'Поступове зменшення підказок (фейдинг)', 'ru-UA' => 'Постепенное сокращение подсказок (фейдинг)'],
        'verbal_operants' => ['en-US' => 'Verbal operants', 'pt-BR' => 'Operantes verbais', 'es-419' => 'Operantes verbales', 'uk-UA' => 'Вербальні операнти', 'ru-UA' => 'Вербальные операнты'],
        'mand' => ['en-US' => 'Mand', 'pt-BR' => 'Mando (mand)', 'es-419' => 'Mando (mand)', 'uk-UA' => 'Манд', 'ru-UA' => 'Манд'],
        'tact' => ['en-US' => 'Tact', 'pt-BR' => 'Tato (tact)', 'es-419' => 'Tacto (tact)', 'uk-UA' => 'Такт', 'ru-UA' => 'Такт'],
        'echoic' => ['en-US' => 'Echoic', 'pt-BR' => 'Ecoico', 'es-419' => 'Ecoica', 'uk-UA' => 'Ехо-реакція', 'ru-UA' => 'Эхо-реакция'],
        'intraverbal' => ['en-US' => 'Intraverbal', 'pt-BR' => 'Intraverbal', 'es-419' => 'Intraverbal', 'uk-UA' => 'Інтравербальна реакція', 'ru-UA' => 'Интравербализация'],
        'initial_competency_assessment' => [
            'en-US' => 'Initial Competency Assessment',
            'pt-BR' => 'Initial Competency Assessment (avaliação inicial de competência)',
            'es-419' => 'Initial Competency Assessment (evaluación inicial de competencia)',
            'uk-UA' => 'Initial Competency Assessment (початкове оцінювання компетентності)',
            'ru-UA' => 'Initial Competency Assessment (первичная оценка компетентности)',
        ],
    ];

    /** @return array<string, array> keyed by file name without extension */
    private function packages(): array
    {
        $out = [];
        foreach (glob(config('examprep.terminology.path').'/*.json') as $f) {
            $out[basename($f, '.json')] = json_decode(file_get_contents($f), true);
        }

        return $out;
    }

    private function assertRejected(array $packages, string $message): void
    {
        try {
            app(TerminologyImporter::class)->import(array_values($packages));
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, implode(' ', array_merge(...array_values($e->errors()))));
        }
    }

    public function test_ten_concepts_and_ten_records_per_locale_are_imported(): void
    {
        $this->seedBank();

        $this->assertSame(10, TerminologyTerm::distinct()->count('term_key'));
        $this->assertSame(50, TerminologyTerm::count());
        foreach (['en-US', 'pt-BR', 'es-419', 'uk-UA', 'ru-UA'] as $locale) {
            $this->assertSame(10, TerminologyTerm::where('locale', $locale)->count(), $locale);
        }
        $this->assertSame(0, TerminologyTerm::whereNotIn('locale', ['en-US', 'pt-BR', 'es-419', 'uk-UA', 'ru-UA'])->count());
        $this->assertSame(['1.0'], TerminologyTerm::distinct()->pluck('dictionary_version')->all());
    }

    public function test_terms_match_the_packages_exactly(): void
    {
        $this->seedBank();

        foreach (self::EXPECTED as $key => $locales) {
            foreach ($locales as $locale => $term) {
                $this->assertSame($term, TerminologyTerm::where(['term_key' => $key, 'locale' => $locale])->value('localized_term'), "{$key} {$locale}");
            }
        }
    }

    public function test_status_and_review_follow_each_package(): void
    {
        $this->seedBank();

        $this->assertSame(10, TerminologyTerm::where(['locale' => 'en-US', 'status' => 'canonical', 'review_status' => 'not_applicable'])->count());
        foreach (['pt-BR', 'es-419'] as $locale) {
            $this->assertSame(10, TerminologyTerm::where(['locale' => $locale, 'status' => 'staging', 'review_status' => 'required'])->count(), $locale);
        }
        $this->assertSame(20, TerminologyTerm::whereIn('locale', ['uk-UA', 'ru-UA'])
            ->where(['status' => 'approved', 'review_status' => 'completed', 'reviewer' => 'Yevheniia Lobas'])
            ->whereDate('effective_on', '2026-10-09')->count());

        $this->assertFalse(TerminologyTerm::where(['term_key' => 'mand', 'locale' => 'pt-BR'])->first()->isReleasable());
        $this->assertTrue(TerminologyTerm::where(['term_key' => 'mand', 'locale' => 'uk-UA'])->first()->isReleasable());
        $this->assertStringContainsString('esvanecimento de dicas', TerminologyTerm::where(['term_key' => 'prompt_fading', 'locale' => 'pt-BR'])->value('notes'));
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->seedBank();

        $report = app(TerminologyImporter::class)->import(array_values($this->packages()));

        $this->assertCount(50, $report['unchanged']);
        $this->assertSame(50, TerminologyTerm::count());
    }

    public function test_a_controlled_term_is_never_overwritten_in_place(): void
    {
        $this->seedBank();
        $packages = $this->packages();
        $packages['pt-BR']['terms'][5]['localized_term'] = 'Mando';

        $this->assertRejected($packages, 'Create a new dictionary version');
        $this->assertSame('Mando (mand)', TerminologyTerm::where(['term_key' => 'mand', 'locale' => 'pt-BR'])->value('localized_term'));
    }

    public function test_review_outcome_is_published_as_a_new_version_and_the_old_one_is_kept(): void
    {
        $this->seedBank();
        $pt = $this->packages()['pt-BR'];
        $pt['dictionary_version'] = '1.1';
        $pt['review_status'] = 'completed';
        $pt['terms'] = array_map(fn ($t) => ['status' => 'approved'] + $t, $pt['terms']);
        $pt['terms'][5]['localized_term'] = 'Mando';
        $en = $this->packages()['en-US'];
        $en['dictionary_version'] = '1.1';

        app(TerminologyImporter::class)->import([$en, $pt]);

        $this->assertSame('Mando (mand)', TerminologyTerm::where(['dictionary_version' => '1.0', 'term_key' => 'mand', 'locale' => 'pt-BR'])->value('localized_term'));
        $this->assertSame('Mando', TerminologyTerm::lookup('mand', 'pt'));
    }

    public function test_ru_ru_and_other_locales_are_rejected(): void
    {
        foreach (['ru-RU', 'es-LATAM', 'ukr'] as $bad) {
            $packages = $this->packages();
            $packages['uk-UA_ru-UA']['terms'][0]['locale'] = $bad;

            $this->assertRejected($packages, 'ru-RU is not valid');
        }
        $this->assertSame(0, TerminologyTerm::count());
    }

    public function test_duplicate_key_and_locale_pairs_are_rejected_across_packages(): void
    {
        $packages = $this->packages();
        $packages['extra'] = $packages['en-US'];

        $this->assertRejected($packages, 'Duplicate term_key + locale: extinction|en-US');
        $this->assertSame(0, TerminologyTerm::count());
    }

    public function test_source_term_must_match_the_canonical_english_term(): void
    {
        $packages = $this->packages();
        $packages['es-419']['terms'][1]['source_term_en'] = 'Burst';

        $this->assertRejected($packages, 'does not match the canonical en-US term');
    }

    public function test_status_rules(): void
    {
        $packages = $this->packages();
        $packages['es-419']['terms'][0]['status'] = 'approved';
        $this->assertRejected($packages, 'needs a completed language review');

        $packages = $this->packages();
        $packages['en-US']['terms'][0]['status'] = 'staging';
        $this->assertRejected($packages, 'not allowed for en-US');
    }

    public function test_bacb_titles_stay_in_english(): void
    {
        $packages = $this->packages();
        $packages['pt-BR']['terms'][9]['localized_term'] = 'Avaliação inicial de competência';

        $this->assertRejected($packages, 'must stay in English');
    }

    public function test_lookup_maps_app_locales_and_falls_back_to_english(): void
    {
        $this->seedBank();

        $this->assertSame('Extinction', TerminologyTerm::lookup('extinction', 'en'));
        $this->assertSame('Extinção', TerminologyTerm::lookup('extinction', 'pt'));
        $this->assertSame('Extinción', TerminologyTerm::lookup('extinction', 'es'));
        $this->assertSame('Згасання', TerminologyTerm::lookup('extinction', 'uk'));
        $this->assertSame('Гашение', TerminologyTerm::lookup('extinction', 'ru-UA'));
        $this->assertSame('Extinction', TerminologyTerm::lookup('extinction', 'fr'));
        $this->assertNull(TerminologyTerm::lookup('unknown_key', 'uk'));
    }

    public function test_command_dry_run_writes_nothing(): void
    {
        $this->artisan('terms:import', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, TerminologyTerm::count());
    }
}
