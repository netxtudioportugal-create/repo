<?php

namespace Database\Seeders;

use App\Services\ItemImporter;
use App\Services\TerminologyImporter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(ItemImporter $importer, TerminologyImporter $terminology): void
    {
        $this->call(TaxonomySeeder::class);

        $packages = array_map(
            fn ($f) => json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR),
            glob(config('examprep.terminology.path').'/*.json'),
        );
        $terms = $terminology->import($packages);
        $this->command?->info(sprintf(
            'Terminology: %d created, %d unchanged.', count($terms['created']), count($terms['unchanged']),
        ));

        $bank = json_decode(file_get_contents(config('examprep.items_file')), true, flags: JSON_THROW_ON_ERROR);
        $report = $importer->import($bank);

        $this->command?->info(sprintf(
            'Items: %d created, %d updated, %d unchanged. All new or changed items start as draft.',
            count($report['created']), count($report['updated']), count($report['unchanged']),
        ));
    }
}
