<?php

namespace App\Services;

use App\Models\TerminologyTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Imports the controlled ABA terminology dictionary from one or more import
 * packages (database/data/terminology/*.json, one per source document).
 *
 * - All or nothing: any invalid record in any package rejects the whole import.
 * - Locales are limited to the set frozen at Gate 0 (en-US, pt-BR, es-419,
 *   uk-UA, ru-UA); ru-RU is rejected by name.
 * - term_key + locale must be unique within a version, across packages.
 * - en-US is the canonical source: status "canonical", and every other
 *   locale's source_term_en must match the en-US term for the same key.
 * - Other locales are "staging" (language review required before release)
 *   or "approved" (review completed, reviewer named).
 * - Same version, same content: left alone, so the import is safe on every deploy.
 * - Same version, different content: rejected. A controlled term is never
 *   overwritten in place; publish it as a new dictionary_version instead.
 * - BACB titles stay in English: a term whose English label is a protected
 *   title must start with that English title (explanation may follow in parentheses).
 */
class TerminologyImporter
{
    private const COMPARED = [
        'dictionary_id', 'source_term_en', 'localized_term', 'status', 'review_status',
        'reviewer', 'approved_by', 'effective_on', 'notes',
    ];

    /**
     * @param  list<array>  $packages
     * @return array{created: list<string>, unchanged: list<string>}
     */
    public function import(array $packages, bool $dryRun = false): array
    {
        foreach ($packages as $p => $package) {
            $this->validatePackage($package, $p);
        }
        $rows = collect($packages)->flatMap(fn ($package) => array_map(fn ($t) => $this->row($package, $t), $package['terms']));
        $this->validateAcrossPackages($rows->all());

        $report = ['created' => [], 'unchanged' => []];

        DB::transaction(function () use ($rows, $dryRun, &$report) {
            $existing = TerminologyTerm::whereIn('dictionary_version', $rows->pluck('dictionary_version')->unique())
                ->lockForUpdate()->get()
                ->keyBy(fn ($t) => $t->dictionary_version.'|'.$t->term_key.'|'.$t->locale);

            $changed = [];
            foreach ($rows as $row) {
                $id = $row['term_key'].'|'.$row['locale'];

                if ($current = $existing->get($row['dictionary_version'].'|'.$id)) {
                    $stored = $current->only(self::COMPARED);
                    $stored['effective_on'] = $current->effective_on?->toDateString();
                    if (array_intersect_key($row, $stored) != $stored) {
                        $changed[$id] = ["{$id} is already controlled in version {$row['dictionary_version']}. Create a new dictionary version instead of overwriting it."];
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

    private function row(array $package, array $term): array
    {
        return [
            'dictionary_id' => $package['dictionary_id'],
            'dictionary_version' => $package['dictionary_version'],
            'term_key' => $term['term_key'],
            'source_term_en' => $term['source_term_en'],
            'locale' => $term['locale'],
            'localized_term' => $term['localized_term'],
            'status' => $term['status'],
            'review_status' => $package['review_status'],
            'reviewer' => $package['reviewer'] ?? null,
            'approved_by' => $package['approved_by'] ?? null,
            'effective_on' => $package['effective_on'] ?? null,
            'notes' => $term['notes'] ?? null,
            'source_package' => $package['package'] ?? null,
        ];
    }

    private function validatePackage(array $package, int $p): void
    {
        $locales = implode(',', array_keys(config('examprep.terminology.statuses')));
        $localeMessage = 'Locale must be one of en-US, pt-BR, es-419, uk-UA, ru-UA. ru-RU is not valid.';
        $name = $package['package'] ?? "package {$p}";

        $validator = Validator::make($package, [
            'dictionary_id' => ['required', 'string', 'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/'],
            'dictionary_version' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'package' => ['nullable', 'string'],
            'effective_on' => ['nullable', 'date_format:Y-m-d'],
            'reviewer' => ['nullable', 'string', 'required_if:review_status,completed'],
            'approved_by' => ['nullable', 'string'],
            'review_status' => ['required', 'in:not_applicable,required,completed'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['in:'.$locales],
            'terms' => ['required', 'array', 'min:1'],
            'terms.*.term_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(_[a-z0-9]+)*$/'],
            'terms.*.source_term_en' => ['required', 'string'],
            'terms.*.locale' => ['required', 'in:'.$locales],
            'terms.*.localized_term' => ['required', 'string'],
            'terms.*.status' => ['required', 'in:canonical,staging,approved'],
            'terms.*.notes' => ['nullable', 'string'],
        ], [
            'terms.*.locale.in' => $localeMessage,
            'locales.*.in' => $localeMessage,
        ]);

        $validator->after(function ($v) use ($package) {
            $statuses = config('examprep.terminology.statuses');
            $titles = $this->protectedTitles(config('examprep.terminology.bacb_titles'));

            foreach ($package['terms'] ?? [] as $i => $term) {
                $locale = $term['locale'] ?? null;
                $status = $term['status'] ?? null;

                if (! in_array($locale, $package['locales'] ?? [], true)) {
                    $v->errors()->add("terms.{$i}.locale", "Locale {$locale} is not listed in this package's locales.");
                }

                if (isset($statuses[$locale]) && ! in_array($status, $statuses[$locale], true)) {
                    $v->errors()->add("terms.{$i}.status", "Status {$status} is not allowed for {$locale}.");
                }

                if ($status === 'approved' && ($package['review_status'] ?? null) !== 'completed') {
                    $v->errors()->add("terms.{$i}.status", 'An approved term needs a completed language review.');
                }

                $en = $term['source_term_en'] ?? '';
                if (in_array(mb_strtolower($en), $titles, true)
                    && ! str_starts_with($term['localized_term'] ?? '', $en)) {
                    $v->errors()->add("terms.{$i}.localized_term", "BACB title \"{$en}\" must stay in English; a localized explanation may follow in parentheses.");
                }
            }
        });

        try {
            $validator->validate();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($m, $field) => ["{$name}: {$field}" => $m])->all());
        }
    }

    private function validateAcrossPackages(array $rows): void
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['dictionary_version'].'|'.$row['term_key'].'|'.$row['locale'];
            if (isset($seen[$id])) {
                $errors[$id][] = "Duplicate term_key + locale: {$row['term_key']}|{$row['locale']}.";
            }
            $seen[$id] = true;
        }

        // Source term consistency with the canonical en-US record, from this
        // import or, failing that, from the database.
        $canonical = collect($rows)->where('locale', 'en-US')
            ->mapWithKeys(fn ($r) => [$r['dictionary_version'].'|'.$r['term_key'] => $r['localized_term']]);
        foreach ($rows as $row) {
            $key = $row['dictionary_version'].'|'.$row['term_key'];
            $en = $canonical[$key] ?? TerminologyTerm::where([
                'dictionary_version' => $row['dictionary_version'], 'term_key' => $row['term_key'], 'locale' => 'en-US',
            ])->value('localized_term');

            if ($en !== null && $en !== $row['source_term_en']) {
                $errors[$row['term_key'].'|'.$row['locale']][] = "source_term_en \"{$row['source_term_en']}\" does not match the canonical en-US term \"{$en}\".";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Titles match with or without the "RBT " prefix, case-insensitively. */
    private function protectedTitles(array $titles): array
    {
        return collect($titles)
            ->flatMap(fn ($t) => [mb_strtolower($t), mb_strtolower(preg_replace('/^RBT\s+/', '', $t))])
            ->unique()->values()->all();
    }
}
