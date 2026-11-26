<?php

namespace App\Console\Commands;

use App\Services\ItemImporter;
use App\Services\TerminologyImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PilotImport extends Command
{
    protected $signature = 'pilot:import
        {--batch= : Content batch: ethics (default) or domain-b}
        {--step= : Step of that batch to run}
        {--dry-run : Validate and report without writing}';

    protected $description = 'Run one import step of a content batch in one transaction, then print the evidence. Run the steps in order, one at a time.';

    public function handle(ItemImporter $items, TerminologyImporter $terminology): int
    {
        $name = $this->option('batch') ?: config('examprep.pilot.default');
        $batch = config("examprep.pilot.batches.{$name}");
        if (! $batch) {
            $this->error("Unknown batch {$name}. Batches: ".implode(', ', array_keys(config('examprep.pilot.batches'))).'.');

            return self::FAILURE;
        }

        $step = (int) $this->option('step');
        if (! in_array($step, $batch['steps'], true)) {
            $this->error("Choose a step of {$batch['label']} with --step=".implode(', ', $batch['steps']).'.');

            return self::FAILURE;
        }

        $file = json_decode(file_get_contents("{$batch['path']}/step-{$step}.json"), true, flags: JSON_THROW_ON_ERROR);
        $this->info("{$batch['label']}. Phase {$file['phase']}, step {$step}: {$file['contains']}");

        try {
            isset($file['terminology'])
                ? $this->terminology($terminology, $file['terminology'])
                : $this->items($items, $file);
        } catch (ValidationException $e) {
            $this->error('Import rejected. Nothing was written.');
            foreach ($e->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        return $this->call('pilot:evidence', ['--batch' => $name]);
    }

    private function terminology(TerminologyImporter $importer, array $packages): void
    {
        $report = $importer->import($packages, (bool) $this->option('dry-run'));

        $byLocale = fn (array $ids) => collect($ids)->countBy(fn ($id) => explode('|', $id)[1]);
        $created = $byLocale($report['created']);
        $unchanged = $byLocale($report['unchanged']);

        $this->table(['Terminology', 'Created', 'Unchanged'], $created->keys()->merge($unchanged->keys())->unique()->sort()->map(
            fn ($l) => [$l, $created[$l] ?? 0, $unchanged[$l] ?? 0],
        )->values()->all());
    }

    private function items(ItemImporter $importer, array $file): void
    {
        $report = $importer->import($file, (bool) $this->option('dry-run'));

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
    }
}
