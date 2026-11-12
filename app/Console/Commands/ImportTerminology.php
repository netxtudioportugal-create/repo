<?php

namespace App\Console\Commands;

use App\Services\TerminologyImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportTerminology extends Command
{
    protected $signature = 'terms:import {path? : Package JSON file or folder (default: database/data/terminology)} {--dry-run : Validate and report without writing}';

    protected $description = 'Import the ABA terminology dictionary packages (en-US, pt-BR, es-419, uk-UA, ru-UA) in one transaction. Controlled terms are never overwritten in place.';

    public function handle(TerminologyImporter $importer): int
    {
        $path = $this->argument('path') ?? config('examprep.terminology.path');
        $files = is_dir($path) ? glob(rtrim($path, '/').'/*.json') : (is_file($path) ? [$path] : []);

        if (! $files) {
            $this->error("No package files found: {$path}");

            return self::FAILURE;
        }

        $packages = array_map(fn ($f) => json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR), $files);

        try {
            $report = $importer->import($packages, (bool) $this->option('dry-run'));
        } catch (ValidationException $e) {
            $this->error('Import rejected. Nothing was written.');
            foreach ($e->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $byLocale = fn (array $ids) => collect($ids)->countBy(fn ($id) => explode('|', $id)[1]);
        $created = $byLocale($report['created']);
        $unchanged = $byLocale($report['unchanged']);

        $this->table(['Locale', 'Created', 'Unchanged'], $created->keys()->merge($unchanged->keys())->unique()->sort()->map(
            fn ($l) => [$l, $created[$l] ?? 0, $unchanged[$l] ?? 0],
        )->values()->all());

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }
}
