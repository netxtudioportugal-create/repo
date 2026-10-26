<?php

namespace Database\Seeders;

use App\Services\ItemImporter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(ItemImporter $importer): void
    {
        $this->call(TaxonomySeeder::class);

        $bank = json_decode(file_get_contents(config('examprep.items_file')), true, flags: JSON_THROW_ON_ERROR);
        $report = $importer->import($bank);

        $this->command?->info(sprintf(
            'Items: %d created, %d updated, %d unchanged. All new or changed items start as draft.',
            count($report['created']), count($report['updated']), count($report['unchanged']),
        ));
    }
}
