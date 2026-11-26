<?php

namespace App\Console\Commands;

use App\Services\ItemImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportItems extends Command
{
    protected $signature = 'items:import {path? : JSON item bank (default: database/data/items.json)} {--dry-run : Validate and report without writing}';

    protected $description = 'Import an item file (masters and language records) in one transaction. Changed masters get a new revision and go back to draft.';

    public function handle(ItemImporter $importer): int
    {
        $path = $this->argument('path') ?? config('examprep.items_file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $bank = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        try {
            $report = $importer->import($bank, (bool) $this->option('dry-run'));
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
        $this->line(count($report['stale']).' language record(s) turned stale.');

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }
}
