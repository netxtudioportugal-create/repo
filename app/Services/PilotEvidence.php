<?php

namespace App\Services;

use App\Enums\LanguageStatus;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemTranslation;
use App\Models\Task;
use App\Models\TerminologyTerm;
use Illuminate\Support\Facades\DB;

/**
 * Read-only figures to record as evidence after each import step
 * (Ethics Pilot, Checklist v1.7).
 */
class PilotEvidence
{
    public function collect(string $domain = 'F'): array
    {
        $allowed = array_keys(config('examprep.locales'));
        $fallback = config('examprep.fallback_locale');

        $inUse = collect()
            ->merge(DB::table('item_translations')->distinct()->pluck('locale'))
            ->merge(DB::table('learners')->distinct()->pluck('locale'))
            ->merge(DB::table('terminology_terms')->distinct()->pluck('locale'))
            ->merge(Domain::all()->flatMap(fn ($d) => array_keys($d->names)))
            ->merge(Task::all()->flatMap(fn ($t) => array_keys($t->labels)))
            ->unique()->sort()->values();

        $items = Item::whereHas('domain', fn ($q) => $q->where('code', $domain))->with('translations')->orderBy('code')->get();

        $languages = [];
        foreach (array_diff($allowed, [$fallback]) as $locale) {
            $row = ['locale' => $locale, 'current' => 0, 'history' => 0, 'history_stale' => 0, 'reviewers' => []];
            foreach (LanguageStatus::cases() as $s) {
                $row[$s->value] = 0;
            }
            foreach ($items as $item) {
                $records = $item->translations->where('locale', $locale)->sortByDesc('version')->values();
                if ($current = $records->first()) {
                    $row['current']++;
                    $row[$current->language_status->value]++;
                    if ($current->language_reviewer) {
                        $row['reviewers'][$current->language_reviewer.' '.$current->language_reviewed_on?->toDateString()] = true;
                    }
                }
                $row['history'] += max(0, $records->count() - 1);
                $row['history_stale'] += $records->slice(1)->filter->isStale()->count();
            }
            $row['reviewers'] = implode('; ', array_map('trim', array_keys($row['reviewers'])));
            $languages[] = $row;
        }

        return [
            'locales' => [
                'in_use' => $inUse->all(),
                'unexpected' => $inUse->diff($allowed)->values()->all(),
            ],
            'counts' => [
                'items' => Item::count(),
                'language_records' => ItemTranslation::count(),
                'tasks' => Task::count(),
                'tasks_with_official_wording' => Task::whereNotNull('official_wording')->count(),
                'terminology_records' => TerminologyTerm::count(),
                'terminology_duplicates' => DB::table('terminology_terms')
                    ->select('dictionary_version', 'term_key', 'locale')
                    ->groupBy('dictionary_version', 'term_key', 'locale')->havingRaw('count(*) > 1')->get()->count(),
                'language_record_duplicates' => DB::table('item_translations')
                    ->select('item_id', 'locale', 'version')
                    ->groupBy('item_id', 'locale', 'version')->havingRaw('count(*) > 1')->get()->count(),
            ],
            'masters' => $items->map(fn (Item $i) => [
                'item' => $i->code,
                'master_version' => $i->master_version,
                'status' => $i->status->value,
                'stale' => $i->currentTranslations()->filter->isStale()->keys()->join(', '),
            ])->all(),
            'languages' => $languages,
        ];
    }
}
