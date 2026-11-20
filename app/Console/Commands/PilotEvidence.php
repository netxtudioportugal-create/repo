<?php

namespace App\Console\Commands;

use App\Services\PilotEvidence as Evidence;
use Illuminate\Console\Command;

class PilotEvidence extends Command
{
    protected $signature = 'pilot:evidence {--domain=F : Domain whose items are listed} {--json : Print JSON instead of tables}';

    protected $description = 'Print the figures to record as evidence after an import step: locale set, counts, master versions and language review status. Read only.';

    public function handle(Evidence $evidence): int
    {
        $data = $evidence->collect($this->option('domain'));

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Locale set in use: '.implode(', ', $data['locales']['in_use']));
        $data['locales']['unexpected']
            ? $this->error('Not in the frozen set: '.implode(', ', $data['locales']['unexpected']))
            : $this->line('No locale outside en-US, pt-BR, es-419, uk-UA, ru-UA.');

        $this->table(['Count', 'Value'], collect($data['counts'])->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all());

        $this->table(['Item', 'master_version', 'Status', 'Stale current records'], $data['masters']);

        $this->table(
            ['Locale', 'Current', 'draft', 'in_language_review', 'approved', 'stale', 'Reviewer of current records', 'History rows', 'of which stale'],
            array_map(fn ($r) => [
                $r['locale'], $r['current'], $r['draft'], $r['in_language_review'], $r['approved'], $r['stale'],
                $r['reviewers'], $r['history'], $r['history_stale'],
            ], $data['languages']),
        );

        return self::SUCCESS;
    }
}
