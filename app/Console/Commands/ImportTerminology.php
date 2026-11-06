<?php

namespace App\Console\Commands;

use App\Services\TerminologyImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportTerminology extends Command
{
    protected $signature = 'terms:import {path? : JSON dictionary (default: database/data/terminology.json)} {--dry-run : Validate and report without writing}';

    protected $description = 'Import the approved uk-UA / ru-UA ABA terminology dictionary in one transaction. Approved terms are never overwritten in place.';

    public function handle(TerminologyImporter $importer): int
    {
        $path = $this->argument('path') ?? config('examprep.terminology_file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $dictionary = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        try {
            $report = $importer->import($dictionary, (bool) $this->option('dry-run'));
        } catch (ValidationException $e) {
            $this->error('Import rejected. Nothing was written.');
            foreach ($e->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->info("Dictionary {$dictionary['dictionary_id']} version {$dictionary['dictionary_version']}");
        $this->table(['Result', 'Count'], collect($report)->map(fn ($ids, $k) => [$k, count($ids)])->values()->all());

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }
}
