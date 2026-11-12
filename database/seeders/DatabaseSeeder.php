<?php

namespace Database\Seeders;

use App\Services\ItemImporter;
use App\Services\TerminologyImporter;
use Illuminate\Database\Seeder;

/**
 * A complete local or test database: taxonomy, terminology, the A to E bank
 * and every Ethics Pilot step in order. On staging the pilot steps are run by
 * hand with pilot:import, one at a time, so the evidence can be recorded.
 */
class DatabaseSeeder extends Seeder
{
    public function run(ItemImporter $importer, TerminologyImporter $terminology): void
    {
        $this->call(TaxonomySeeder::class);

        $read = fn (string $f) => json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR);

        $terms = $terminology->import(array_map($read, glob(config('examprep.terminology.path').'/*.json')));
        $this->command?->info(sprintf(
            'Terminology: %d created, %d unchanged.', count($terms['created']), count($terms['unchanged']),
        ));

        $files = [config('examprep.items_file')];
        foreach (config('examprep.pilot.steps') as $step) {
            $files[] = config('examprep.pilot.path')."/step-{$step}.json";
        }

        foreach ($files as $file) {
            $report = $importer->import($read($file));
            $this->command?->info(sprintf(
                '%s: masters %d created, %d updated; language records %d created, %d updated; %d turned stale.',
                basename($file),
                count($report['masters']['created']), count($report['masters']['updated']),
                count($report['languages']['created']), count($report['languages']['updated']),
                count($report['stale']),
            ));
        }
    }
}
