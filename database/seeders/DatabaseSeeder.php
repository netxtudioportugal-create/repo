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
        foreach (config('examprep.pilot.batches') as $batch) {
            foreach ($batch['steps'] as $step) {
                $files[] = "{$batch['path']}/step-{$step}.json";
            }
        }

        foreach ($files as $file) {
            $data = $read($file);
            if (isset($data['terminology'])) {
                $terms = $terminology->import($data['terminology']);
                $this->command?->info(sprintf('%s: terminology %d created.', basename(dirname($file)).'/'.basename($file), count($terms['created'])));

                continue;
            }
            $report = $importer->import($data);
            $this->command?->info(sprintf(
                '%s: masters %d created, %d updated; language records %d created, %d updated; %d turned stale.',
                basename(dirname($file)).'/'.basename($file),
                count($report['masters']['created']), count($report['masters']['updated']),
                count($report['languages']['created']), count($report['languages']['updated']),
                count($report['stale']),
            ));
        }
    }
}
