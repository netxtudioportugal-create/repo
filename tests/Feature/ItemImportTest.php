<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\LanguageStatus;
use App\Models\Item;
use App\Models\ItemTranslation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The A to E bank (database/data/items.json) and the general import rules. */
class ItemImportTest extends TestCase
{
    private function bank(): array
    {
        return $this->readJson(config('examprep.items_file'));
    }

    public function test_bank_and_pilot_import_as_drafts(): void
    {
        $this->seedBank();

        $this->assertSame(24, Item::count());
        $this->assertSame(24, Item::where('status', ItemStatus::Draft->value)->count());
        $this->assertSame('D', Item::where('code', 'F-02-001')->first()->getAttribute('key'));

        $a01 = Item::where('code', 'A-01')->first();
        $this->assertSame(1, $a01->master_version);
        $this->assertEqualsCanonicalizing(['en-US', 'pt-BR', 'es-419', 'uk-UA', 'ru-UA'], $a01->translations->pluck('locale')->all());
        $this->assertNull($a01->translations->firstWhere('locale', 'en-US')->language_status);
        $this->assertSame(LanguageStatus::Draft, $a01->translations->firstWhere('locale', 'uk-UA')->language_status);
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->seedBank();
        $rows = ItemTranslation::count();

        $report = $this->importFile($this->bank());

        $this->assertCount(10, $report['masters']['unchanged']);
        $this->assertCount(40, $report['languages']['unchanged']);
        $this->assertSame($rows, ItemTranslation::count());
        $this->assertSame(1, Item::where('code', 'A-01')->value('version'));
    }

    public function test_records_written_by_the_earlier_importer_are_not_seen_as_changed(): void
    {
        $this->seedBank();
        Item::query()->update(['content_hash' => str_repeat('0', 64)]);

        $report = $this->importFile($this->bank());

        $this->assertCount(10, $report['masters']['unchanged']);
        $this->assertSame(1, Item::where('code', 'A-01')->value('version'));
    }

    public function test_changed_master_text_needs_a_higher_master_version_when_translations_exist(): void
    {
        $this->seedBank();
        $bank = $this->bank();
        $bank['masters'][0]['stem'] .= ' (edited)';

        try {
            $this->importFile($bank);
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Raise master_version', implode(' ', $e->errors()['A-01']));
        }
        $this->assertSame(1, Item::where('code', 'A-01')->value('version'));
    }

    public function test_higher_master_version_restarts_review_and_turns_translations_stale(): void
    {
        $this->seedBank();
        $this->release('A-01');
        $bank = $this->bank();
        $bank['masters'][0]['stem'] .= ' (edited)';
        $bank['masters'][0]['master_version'] = 2;
        $bank['languages'] = [];

        $report = $this->importFile($bank);

        $item = Item::where('code', 'A-01')->first();
        $this->assertSame(2, $item->master_version);
        $this->assertSame(2, $item->version);
        $this->assertSame(ItemStatus::Draft, $item->status);
        $this->assertSame(2, $item->versions()->count());
        $this->assertCount(4, $report['stale']);
        $this->assertSame(4, $item->translations()->where('language_status', 'stale')->count());
        [$content, $fallback] = $item->contentFor('pt-BR');
        $this->assertTrue($fallback);
        $this->assertSame('en-US', $content->locale);
    }

    public function test_one_invalid_row_rejects_the_whole_file(): void
    {
        $this->seedBank();
        $bank = $this->bank();
        $bank['masters'][0]['master_version'] = 2;
        $bank['masters'][0]['stem'] .= ' (edited)';
        $bank['masters'][1]['options']['B']['is_key'] = true; // two keys

        try {
            $this->importFile($bank);
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException) {
        }

        $this->assertSame(1, Item::where('code', 'A-01')->value('master_version'));
    }

    public function test_import_never_sets_a_master_status_other_than_draft(): void
    {
        $this->seedTaxonomy();
        $bank = $this->bank();
        $bank['masters'][0]['status'] = 'released';

        $this->expectException(ValidationException::class);
        $this->importFile($bank);
    }

    public function test_review_fields_are_never_changed_by_an_import(): void
    {
        $this->seedBank();
        Item::where('code', 'A-01')->update(['ip_clearance' => 'cleared', 'source_review_hold' => true, 'status' => 'approved']);
        $bank = $this->bank();
        $bank['masters'][0]['key_concept'] = 'Edited metadata only';

        $this->importFile($bank);

        $item = Item::where('code', 'A-01')->first();
        $this->assertSame('Edited metadata only', $item->key_concept);
        $this->assertSame('cleared', $item->ip_clearance);
        $this->assertTrue($item->source_review_hold);
        $this->assertSame(ItemStatus::Approved, $item->status);
    }
}
