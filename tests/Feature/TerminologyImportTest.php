<?php

namespace Tests\Feature;

use App\Models\TerminologyTerm;
use App\Services\TerminologyImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mirrors the validation checklist of the uk-UA / ru-UA import package v2. */
class TerminologyImportTest extends TestCase
{
    private function dictionary(): array
    {
        return json_decode(file_get_contents(config('examprep.terminology_file')), true);
    }

    private function assertRejected(array $dictionary, string $message): void
    {
        try {
            app(TerminologyImporter::class)->import($dictionary);
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, implode(' ', array_merge(...array_values($e->errors()))));
        }
    }

    public function test_ten_concepts_and_twenty_records_are_imported(): void
    {
        $this->seedBank();

        $this->assertSame(10, TerminologyTerm::distinct()->count('term_key'));
        $this->assertSame(20, TerminologyTerm::count());
        $this->assertSame(10, TerminologyTerm::where('locale', 'ru-UA')->count());
        $this->assertSame(10, TerminologyTerm::where('locale', 'uk-UA')->count());
        $this->assertSame(0, TerminologyTerm::where('locale', 'ru-RU')->count());
        $this->assertSame(['1.0'], TerminologyTerm::distinct()->pluck('dictionary_version')->all());
        $this->assertSame(20, TerminologyTerm::where('status', 'approved')->where('reviewer', 'Yevheniia Lobas')
            ->whereDate('effective_on', '2026-10-09')->count());
    }

    public function test_approved_terms_match_the_package_exactly(): void
    {
        $this->seedBank();

        $expected = [
            'extinction' => ['ru-UA' => 'Гашение', 'uk-UA' => 'Згасання'],
            'extinction_burst' => ['ru-UA' => 'Угасательное обострение', 'uk-UA' => 'Сплеск згасання'],
            'escape_extinction' => ['ru-UA' => 'Гашение избегающего поведения', 'uk-UA' => 'Згасання поведінки, підтримуваної втечею від вимог або ситуації'],
            'prompt_fading' => ['ru-UA' => 'Постепенное сокращение подсказок (фейдинг)', 'uk-UA' => 'Поступове зменшення підказок (фейдинг)'],
            'verbal_operants' => ['ru-UA' => 'Вербальные операнты', 'uk-UA' => 'Вербальні операнти'],
            'mand' => ['ru-UA' => 'Манд', 'uk-UA' => 'Манд'],
            'tact' => ['ru-UA' => 'Такт', 'uk-UA' => 'Такт'],
            'echoic' => ['ru-UA' => 'Эхо-реакция', 'uk-UA' => 'Ехо-реакція'],
            'intraverbal' => ['ru-UA' => 'Интравербализация', 'uk-UA' => 'Інтравербальна реакція'],
            'initial_competency_assessment' => [
                'ru-UA' => 'Initial Competency Assessment (первичная оценка компетентности)',
                'uk-UA' => 'Initial Competency Assessment (початкове оцінювання компетентності)',
            ],
        ];

        foreach ($expected as $key => $locales) {
            foreach ($locales as $locale => $term) {
                $this->assertSame($term, TerminologyTerm::where(['term_key' => $key, 'locale' => $locale])->value('approved_term'), "{$key} {$locale}");
            }
        }
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->seedBank();

        $report = app(TerminologyImporter::class)->import($this->dictionary());

        $this->assertCount(20, $report['unchanged']);
        $this->assertSame(20, TerminologyTerm::count());
    }

    public function test_an_approved_term_is_never_overwritten_in_place(): void
    {
        $this->seedBank();
        $dictionary = $this->dictionary();
        $dictionary['terms'][0]['approved_term'] = 'Угасание';

        $this->assertRejected($dictionary, 'Create a new dictionary version');
        $this->assertSame('Гашение', TerminologyTerm::where(['term_key' => 'extinction', 'locale' => 'ru-UA'])->value('approved_term'));
    }

    public function test_a_change_is_published_as_a_new_version_and_the_old_one_is_kept(): void
    {
        $this->seedBank();
        $dictionary = $this->dictionary();
        $dictionary['dictionary_version'] = '1.1';
        $dictionary['terms'][0]['approved_term'] = 'Гашение (процедура)';

        app(TerminologyImporter::class)->import($dictionary);

        $this->assertSame(40, TerminologyTerm::count());
        $this->assertSame('Гашение', TerminologyTerm::where(['dictionary_version' => '1.0', 'term_key' => 'extinction', 'locale' => 'ru-UA'])->value('approved_term'));
        $this->assertSame('Гашение (процедура)', TerminologyTerm::lookup('extinction', 'ru-UA'));
    }

    public function test_ru_ru_is_rejected(): void
    {
        $dictionary = $this->dictionary();
        $dictionary['terms'][0]['locale'] = 'ru-RU';

        $this->assertRejected($dictionary, 'ru-RU is not valid');
        $this->assertSame(0, TerminologyTerm::count());
    }

    public function test_duplicate_key_and_locale_pairs_are_rejected(): void
    {
        $dictionary = $this->dictionary();
        $dictionary['terms'][] = $dictionary['terms'][0];

        $this->assertRejected($dictionary, 'Duplicate term_key + locale: extinction|ru-UA');
        $this->assertSame(0, TerminologyTerm::count());
    }

    public function test_bacb_titles_stay_in_english(): void
    {
        $dictionary = $this->dictionary();
        $last = array_key_last($dictionary['terms']);
        $dictionary['terms'][$last]['approved_term'] = 'Початкове оцінювання компетентності';

        $this->assertRejected($dictionary, 'must stay in English');
    }

    public function test_lookup_maps_app_locales_and_falls_back_to_english(): void
    {
        $this->seedBank();

        $this->assertSame('Згасання', TerminologyTerm::lookup('extinction', 'uk'));
        $this->assertSame('Гашение', TerminologyTerm::lookup('extinction', 'ru-UA'));
        $this->assertSame('Extinction', TerminologyTerm::lookup('extinction', 'pt'));
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
