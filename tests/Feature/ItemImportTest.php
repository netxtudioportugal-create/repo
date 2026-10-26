<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Services\ItemImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    public function test_bank_imports_as_drafts_at_version_one(): void
    {
        $this->seedBank();

        $this->assertSame(24, Item::count());
        $this->assertSame(24, Item::where('status', ItemStatus::Draft->value)->count());
        $this->assertSame('C', Item::where('code', 'F-02-001')->first()->getAttribute('key'));
        $this->assertSame(['en'], Item::where('code', 'F-01-001')->first()->translations->pluck('locale')->all());
    }

    public function test_reimport_is_idempotent(): void
    {
        $this->seedBank();
        $bank = json_decode(file_get_contents(config('examprep.items_file')), true);

        $report = app(ItemImporter::class)->import($bank);

        $this->assertCount(24, $report['unchanged']);
        $this->assertSame(1, Item::where('code', 'A-01')->value('version'));
    }

    public function test_changed_content_bumps_version_and_restarts_review(): void
    {
        $this->seedBank();
        $this->release('A-01');
        $bank = json_decode(file_get_contents(config('examprep.items_file')), true);
        $bank['items'][0]['translations']['en']['stem'] .= ' (edited)';

        app(ItemImporter::class)->import($bank);

        $item = Item::where('code', 'A-01')->first();
        $this->assertSame(2, $item->version);
        $this->assertSame(ItemStatus::Draft, $item->status);
        $this->assertSame(2, $item->versions()->count());
    }

    public function test_one_invalid_item_rejects_the_whole_file(): void
    {
        $this->seedBank();
        $bank = json_decode(file_get_contents(config('examprep.items_file')), true);
        $bank['items'][0]['translations']['en']['stem'] .= ' (edited)';
        $bank['items'][1]['key'] = 'E';

        try {
            app(ItemImporter::class)->import($bank);
            $this->fail('Expected the import to be rejected.');
        } catch (ValidationException) {
        }

        $this->assertSame(1, Item::where('code', 'A-01')->value('version'));
    }
}
