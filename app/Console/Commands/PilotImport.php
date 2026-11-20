<?php

namespace App\Console\Commands;

use App\Services\ItemImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PilotImport extends Command
{
    protected $signature = 'pilot:import {--step= : Import step to run: 3, 4, 5 or 6} {--dry-run : Validate and report without writing}';

    protected $description = 'Run one Ethics Pilot import step (3 to 6) in one transaction, then print the evidence. Run the steps in order, one at a time.';

    public function handle(ItemImporter $importer): int
    {
        $step = (int) $this->option('step');
        if (! in_array($step, config('examprep.pilot.steps'), true)) {
            $this->error('Choose a step with --step=3, 4, 5 or 6. Steps 1 and 2 (locale migration, tasks, terminology) run on deploy.');

            return self::FAILURE;
        }

        $file = json_decode(file_get_contents(config('examprep.pilot.path')."/step-{$step}.json"), true, flags: JSON_THROW_ON_ERROR);
        $this->info("Phase {$file['phase']}, step {$step}: {$file['contains']}");

        try {
            $report = $importer->import($file, (bool) $this->option('dry-run'));
        } catch (ValidationException $e) {
            $this->error('Import rejected. Nothing was written.');
            foreach ($e->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->table(['', 'Created', 'Updated', 'Unchanged', 'Skipped'], [
            ['Masters', ...array_map('count', array_values($report['masters']))],
            ['Language records', ...array_map('count', array_values($report['languages']))],
        ]);
        foreach ([...$report['masters']['skipped'], ...$report['languages']['skipped']] as $skipped) {
            $this->line("  skipped: {$skipped}");
        }

        $this->line(count($report['stale']).' language record(s) turned stale'.($report['stale'] ? ':' : '.'));
        foreach ($report['stale'] as $id) {
            $this->line("  {$id}");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        return $this->call('pilot:evidence');
    }
}
