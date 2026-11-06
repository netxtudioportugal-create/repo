<?php

namespace App\Services;

use App\Models\TerminologyTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Imports the controlled ABA terminology dictionary (database/data/terminology.json).
 *
 * - All or nothing: any invalid record rejects the whole file.
 * - Only uk-UA and ru-UA are accepted; ru-RU is rejected by name.
 * - term_key + locale must be unique within a version.
 * - Same version, same content: left alone, so the import is safe on every deploy.
 * - Same version, different content: rejected. An approved term is never
 *   overwritten in place; publish it as a new dictionary_version instead.
 * - BACB titles stay in English: a term whose English label is a protected
 *   title must start with that English title (explanation may follow in parentheses).
 */
class TerminologyImporter
{
    public const LOCALES = ['uk-UA', 'ru-UA'];

    private const COMPARED = ['dictionary_id', 'source_term_en', 'approved_term', 'status', 'effective_on', 'reviewer', 'notes'];

    /** @return array{created: list<string>, unchanged: list<string>} */
    public function import(array $dictionary, bool $dryRun = false): array
    {
        $this->validate($dictionary);

        $report = ['created' => [], 'unchanged' => []];
        $version = $dictionary['dictionary_version'];

        DB::transaction(function () use ($dictionary, $version, $dryRun, &$report) {
            $existing = TerminologyTerm::where('dictionary_version', $version)->lockForUpdate()->get()
                ->keyBy(fn ($t) => $t->term_key.'|'.$t->locale);

            $changed = [];
            foreach ($dictionary['terms'] as $i => $term) {
                $row = $this->row($dictionary, $term);
                $id = $term['term_key'].'|'.$term['locale'];

                if ($current = $existing->get($id)) {
                    $stored = $current->only(self::COMPARED);
                    $stored['effective_on'] = $current->effective_on->toDateString();
                    if (array_intersect_key($row, $stored) != $stored) {
                        $changed["terms.{$i}"] = ["{$id} is already approved in version {$version}. Create a new dictionary version instead of overwriting it."];
                    } else {
                        $report['unchanged'][] = $id;
                    }

                    continue;
                }

                if (! $dryRun) {
                    TerminologyTerm::create($row);
                }
                $report['created'][] = $id;
            }

            if ($changed) {
                throw ValidationException::withMessages($changed);
            }
        });

        return $report;
    }

    private function row(array $dictionary, array $term): array
    {
        return [
            'dictionary_id' => $dictionary['dictionary_id'],
            'dictionary_version' => $dictionary['dictionary_version'],
            'term_key' => $term['term_key'],
            'source_term_en' => $term['source_term_en'],
            'locale' => $term['locale'],
            'approved_term' => $term['approved_term'],
            'status' => $term['status'],
            'effective_on' => $dictionary['effective_on'],
            'reviewer' => $dictionary['reviewer'],
            'notes' => $term['notes'] ?? null,
        ];
    }

    private function validate(array $dictionary): void
    {
        $validator = Validator::make($dictionary, [
            'dictionary_id' => ['required', 'string', 'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/'],
            'dictionary_version' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'reviewer' => ['required', 'string'],
            'locales' => ['required', 'array'],
            'locales.*' => ['in:'.implode(',', self::LOCALES)],
            'bacb_titles' => ['array'],
            'bacb_titles.*' => ['string'],
            'terms' => ['required', 'array', 'min:1'],
            'terms.*.term_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/'],
            'terms.*.source_term_en' => ['required', 'string'],
            'terms.*.locale' => ['required', 'in:'.implode(',', self::LOCALES)],
            'terms.*.approved_term' => ['required', 'string'],
            'terms.*.status' => ['required', 'in:approved'],
            'terms.*.notes' => ['nullable', 'string'],
        ], [
            'terms.*.locale.in' => 'Locale must be uk-UA or ru-UA. ru-RU is not valid.',
            'locales.*.in' => 'Locale must be uk-UA or ru-UA. ru-RU is not valid.',
        ]);

        $validator->after(function ($v) use ($dictionary) {
            $seen = [];
            $titles = $this->protectedTitles($dictionary['bacb_titles'] ?? []);

            foreach ($dictionary['terms'] ?? [] as $i => $term) {
                $id = ($term['term_key'] ?? '').'|'.($term['locale'] ?? '');
                if (isset($seen[$id])) {
                    $v->errors()->add("terms.{$i}", "Duplicate term_key + locale: {$id}.");
                }
                $seen[$id] = true;

                if (! in_array($term['locale'] ?? null, $dictionary['locales'] ?? [], true)) {
                    $v->errors()->add("terms.{$i}.locale", "Locale {$term['locale']} is not listed in the dictionary locales.");
                }

                $en = $term['source_term_en'] ?? '';
                if (in_array(mb_strtolower($en), $titles, true)
                    && ! str_starts_with($term['approved_term'] ?? '', $en)) {
                    $v->errors()->add("terms.{$i}.approved_term", "BACB title \"{$en}\" must stay in English; a localized explanation may follow in parentheses.");
                }
            }
        });

        $validator->validate();
    }

    /** Titles match with or without the "RBT " prefix, case-insensitively. */
    private function protectedTitles(array $titles): array
    {
        return collect($titles)
            ->flatMap(fn ($t) => [mb_strtolower($t), mb_strtolower(preg_replace('/^RBT\s+/', '', $t))])
            ->unique()->values()->all();
    }
}
