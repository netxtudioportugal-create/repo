<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\LanguageStatus;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemStatusChange;
use App\Models\ItemTranslation;
use App\Models\ItemVersion;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Transactional item import (Ethics Pilot v3.8, Checklist v1.7).
 *
 * A file has two lists: "masters" (en-US rows) and "languages" (every other
 * locale). Masters are applied first. One invalid row rejects the whole file.
 *
 * Masters
 * - Natural key: item_code. master_version is declared by the file.
 * - New master: created as draft. An import never sets another status, and
 *   never sets a review, release or BCBA field.
 * - Changed text: the content revision rises, a snapshot is stored and the
 *   item goes back to draft, because a reviewed text is not the text that
 *   would be shown.
 * - Higher master_version: language records made from an older master turn
 *   stale. Only the system sets stale.
 * - Changed text with the same master_version is refused while live language
 *   records exist for it: raise master_version so they turn stale.
 * - Lower master_version than the one stored: skipped (an older step re-run).
 *
 * Steps
 * - A file may carry a "step" number. A row from a lower step than the last
 *   one applied to that record is skipped, so re-running an older step never
 *   undoes a newer one.
 *
 * Language records
 * - Natural key: item_code + locale + version. Re-running a file never
 *   creates duplicates. A new version is a new row; older rows stay as history.
 * - key, task, citations and the other master-owned fields always come from
 *   the en-US master, never from a language row.
 * - A record made from an older master than the current one is stale.
 * - An approved text is never changed in place: import it as a new version.
 * - A status set by a reviewer role is never overwritten by an import.
 * - Lower source_master_version than the one stored: skipped.
 */
class ItemImporter
{
    private const LABELS = ['A', 'B', 'C', 'D'];

    private const MASTER_META = [
        'key_concept', 'question_type', 'difficulty_author', 'authority_source', 'authority_version',
        'source_checked_on', 'is_rule_dependent', 'requires_reverification', 'cadence_months',
    ];

    private array $errors = [];

    private ?int $step = null;

    /**
     * @return array{
     *   masters: array{created: list<string>, updated: list<string>, unchanged: list<string>, skipped: list<string>},
     *   languages: array{created: list<string>, updated: list<string>, unchanged: list<string>, skipped: list<string>},
     *   stale: list<string>
     * }
     */
    public function import(array $file, bool $dryRun = false): array
    {
        $this->validate($file);

        $empty = ['created' => [], 'updated' => [], 'unchanged' => [], 'skipped' => []];
        $report = ['masters' => $empty, 'languages' => $empty, 'stale' => []];
        $this->errors = [];
        $this->step = isset($file['step']) ? (int) $file['step'] : null;

        try {
            DB::transaction(function () use ($file, $dryRun, &$report) {
                foreach ($file['masters'] ?? [] as $row) {
                    $this->master($row, $report);
                }
                foreach ($file['languages'] ?? [] as $row) {
                    $this->language($row, $report);
                }

                if ($this->errors) {
                    throw ValidationException::withMessages($this->errors);
                }
                if ($dryRun) {
                    throw new DryRunRollback; // undo everything, keep the report
                }
            });
        } catch (DryRunRollback) {
            // dry run: nothing written
        }

        return $report;
    }

    private function master(array $row, array &$report): void
    {
        $code = $row['item_code'];
        $task = empty($row['task_code']) ? null : Task::where('code', $row['task_code'])->first();
        $domainId = $task?->domain_id ?? Domain::where('code', $row['domain'] ?? null)->value('id');

        if (! empty($row['task_code']) && ! $task) {
            $this->errors[$code][] = "Task {$row['task_code']} is not in the task outline.";
        }
        if (! $domainId) {
            $this->errors[$code][] = 'A master needs a task_code or a domain.';
        }
        if (isset($this->errors[$code])) {
            return;
        }

        $key = collect($row['options'])->filter(fn ($o) => $o['is_key'] ?? false)->keys()->first();
        $target = collect($row['options'])->filter(fn ($o) => $o['is_target_misconception'] ?? false)->keys()->first();
        $version = (int) $row['master_version'];
        $domainCode = Domain::whereKey($domainId)->value('code');
        $hash = $this->hash($row, $domainCode, $key, $target);

        $content = [
            'domain_id' => $domainId,
            'task_id' => $task?->id,
            'key' => $key,
            'target_misconception' => $target,
            'source_ref' => $row['source_standard'] ?? null,
            'content_hash' => $hash,
        ];
        $meta = [];
        foreach (self::MASTER_META as $field) {
            $meta[$field] = $row[$field] ?? ($field === 'is_rule_dependent' || $field === 'requires_reverification' ? false : null);
        }

        $item = Item::where('code', $code)->lockForUpdate()->first();

        if (! $item) {
            $item = Item::create($content + $meta + [
                'code' => $code,
                'version' => 1,
                'master_version' => $version,
                'status' => ItemStatus::Draft,
                'import_sequence' => $this->step,
            ]);
            $this->writeMasterText($item, $row, $hash, null, 'Imported');
            $report['masters']['created'][] = $code;

            return;
        }

        if ($version < $item->master_version) {
            $report['masters']['skipped'][] = "{$code} (file has master_version {$version}, stored is {$item->master_version})";

            return;
        }
        if ($this->olderStep($item->import_sequence)) {
            $report['masters']['skipped'][] = "{$code} (step {$this->step} is older than step {$item->import_sequence}, already applied)";

            return;
        }

        // Compared against the stored text itself, not the stored hash, so records
        // written by an earlier importer are not mistaken for changed ones.
        $textChanged = ! hash_equals($this->storedHash($item), $hash);
        $raised = $version > $item->master_version;

        if ($textChanged && ! $raised && $this->liveLanguageRecords($item)->exists()) {
            $this->errors[$code][] = "The master text changed but master_version is still {$version}, and language records exist for it. Raise master_version so they turn stale.";

            return;
        }

        $from = $item->status->value;
        $item->fill($meta + ['master_version' => $version]);
        if ($textChanged) {
            $item->fill($content + ['version' => $item->version + 1, 'status' => ItemStatus::Draft]);
        }
        $dirty = $item->isDirty();
        $item->fill(['import_sequence' => $this->step ?? $item->import_sequence])->save();

        if ($textChanged) {
            $this->writeMasterText($item, $row, $hash, $from, 'Content changed on import; review restarts');
        } elseif ($raised) {
            $item->translations()->where('locale', config('examprep.fallback_locale'))->update(['source_master_version' => $version]);
        }

        if ($raised) {
            $this->liveLanguageRecords($item, below: $version)->get()->each(function (ItemTranslation $t) use ($code, &$report) {
                $t->update(['language_status' => LanguageStatus::Stale, 'status_set_by' => null]);
                $report['stale'][] = "{$code}|{$t->locale}|v{$t->version}";
            });
        }

        $report['masters'][$dirty ? 'updated' : 'unchanged'][] = $code;
    }

    /** Non-English records that are not stale yet; with $below, only those made from an older master. */
    private function liveLanguageRecords(Item $item, ?int $below = null)
    {
        return $item->translations()
            ->where('locale', '!=', config('examprep.fallback_locale'))
            ->where('language_status', '!=', LanguageStatus::Stale->value)
            ->when($below !== null,
                fn ($q) => $q->where('source_master_version', '<', $below),
                fn ($q) => $q->where('source_master_version', $item->master_version));
    }

    private function writeMasterText(Item $item, array $row, string $hash, ?string $from, string $note): void
    {
        $item->translations()->updateOrCreate(
            ['locale' => config('examprep.fallback_locale'), 'version' => 1],
            $this->text($row, $item->getAttribute('key')) + ['source_master_version' => $item->master_version],
        );

        ItemVersion::create([
            'item_id' => $item->id,
            'version' => $item->version,
            'content_hash' => $hash,
            'snapshot' => $row,
        ]);

        ItemStatusChange::create([
            'item_id' => $item->id,
            'item_version' => $item->version,
            'from_status' => $from,
            'to_status' => ItemStatus::Draft->value,
            'note' => $note,
        ]);
    }

    private function language(array $row, array &$report): void
    {
        $id = "{$row['item_code']}|{$row['locale']}|v{$row['version']}";
        $item = Item::where('code', $row['item_code'])->first();

        if (! $item) {
            $this->errors[$id][] = 'The English master is not imported yet. Import the masters first.';

            return;
        }

        $source = (int) $row['source_master_version'];
        if ($source > $item->master_version) {
            $this->errors[$id][] = "Made from master_version {$source}, but the master is at {$item->master_version}. Import the masters first.";

            return;
        }

        $key = $item->getAttribute('key');
        if (empty($row['options'][$key]['rationale'])) {
            $this->errors[$id][] = "Option {$key} is the key of the master and needs a rationale.";

            return;
        }

        $text = $this->text($row, $key);
        $status = [
            // Made from an older master than the current one: stale, whatever the file says.
            'language_status' => $source < $item->master_version ? LanguageStatus::Stale->value : $row['language_status'],
            'language_reviewer' => $row['language_reviewer'] ?? null,
            'language_reviewed_on' => $row['language_reviewed_on'] ?? null,
            'language_notes' => $row['language_notes'] ?? null,
        ];

        $record = $item->translations()->where('locale', $row['locale'])->where('version', $row['version'])->lockForUpdate()->first();

        if (! $record) {
            $item->translations()->create($text + $status + [
                'locale' => $row['locale'],
                'version' => $row['version'],
                'source_master_version' => $source,
                'import_sequence' => $this->step,
            ]);
            $report['languages']['created'][] = $id;

            return;
        }

        if ($source < $record->source_master_version) {
            $report['languages']['skipped'][] = "{$id} (file was made from master_version {$source}, stored is {$record->source_master_version})";

            return;
        }
        if ($this->olderStep($record->import_sequence)) {
            $report['languages']['skipped'][] = "{$id} (step {$this->step} is older than step {$record->import_sequence}, already applied)";

            return;
        }

        $textChanged = $text != $record->only(array_keys($text));

        if ($textChanged && $record->language_status === LanguageStatus::Approved) {
            $this->errors[$id][] = 'This record is approved. An approved text is never changed in place: import the new text as a new version.';

            return;
        }

        $record->fill($text + ['source_master_version' => $source]);

        // A reviewer's decision stands. So does stale on a record that is still behind the master.
        $keepStatus = $record->status_set_by !== null || ($record->isStale() && $source < $item->master_version);
        if (! $keepStatus) {
            $record->fill($status);
        }

        $report['languages'][$record->isDirty() ? 'updated' : 'unchanged'][] = $id;
        $record->fill(['import_sequence' => $this->step ?? $record->import_sequence])->save();
    }

    private function olderStep(?int $applied): bool
    {
        return $this->step !== null && $applied !== null && $this->step < $applied;
    }

    /** Stem, option texts, key rationale and distractor rationales, in canonical labels. */
    private function text(array $row, string $key): array
    {
        $rationales = collect($row['options'])->except($key)->map(fn ($o) => $o['rationale'] ?? null)->filter()->all();

        return [
            'stem' => $row['stem'],
            'options' => array_map(fn ($o) => $o['text'], $row['options']),
            'rationale' => $row['options'][$key]['rationale'],
            'option_rationales' => $rationales ?: null,
        ];
    }

    private function validate(array $file): void
    {
        $languageLocales = array_diff(array_keys(config('examprep.locales')), [config('examprep.fallback_locale')]);

        $validator = Validator::make($file, [
            'masters' => ['array', 'required_without:languages'],
            'masters.*.item_code' => ['required', 'string', 'max:40', 'distinct'],
            'masters.*.domain' => ['nullable', 'string', 'size:1'],
            'masters.*.task_code' => ['nullable', 'string', 'max:10'],
            'masters.*.master_version' => ['required', 'integer', 'min:1'],
            'masters.*.status' => ['nullable', 'in:draft'],
            'masters.*.source_standard' => ['nullable', 'string', 'max:255'],
            'masters.*.key_concept' => ['nullable', 'string', 'max:255'],
            'masters.*.question_type' => ['nullable', 'string', 'max:20'],
            'masters.*.difficulty_author' => ['nullable', 'integer', 'between:1,5'],
            'masters.*.authority_source' => ['nullable', 'string', 'max:255'],
            'masters.*.authority_version' => ['nullable', 'string', 'max:60'],
            'masters.*.source_checked_on' => ['nullable', 'date_format:Y-m-d'],
            'masters.*.is_rule_dependent' => ['nullable', 'boolean'],
            'masters.*.requires_reverification' => ['nullable', 'boolean'],
            'masters.*.cadence_months' => ['nullable', 'integer', 'min:1'],
            'masters.*.stem' => ['required', 'string'],
            'masters.*.options' => ['required', 'array:A,B,C,D', 'size:4'],
            'masters.*.options.*.text' => ['required', 'string'],
            'masters.*.options.*.rationale' => ['nullable', 'string'],
            'masters.*.options.*.is_key' => ['nullable', 'boolean'],
            'masters.*.options.*.is_target_misconception' => ['nullable', 'boolean'],

            'languages' => ['array'],
            'languages.*.item_code' => ['required', 'string', 'max:40'],
            'languages.*.locale' => ['required', 'in:'.implode(',', $languageLocales)],
            'languages.*.version' => ['required', 'integer', 'min:1'],
            'languages.*.source_master_version' => ['required', 'integer', 'min:1'],
            'languages.*.language_status' => ['required', 'in:'.implode(',', LanguageStatus::settable())],
            'languages.*.language_reviewer' => ['nullable', 'string', 'max:255', 'required_if:languages.*.language_status,approved'],
            'languages.*.language_reviewed_on' => ['nullable', 'date_format:Y-m-d', 'required_if:languages.*.language_status,approved'],
            'languages.*.language_notes' => ['nullable', 'string'],
            'languages.*.stem' => ['required', 'string'],
            'languages.*.options' => ['required', 'array:A,B,C,D', 'size:4'],
            'languages.*.options.*.text' => ['required', 'string'],
            'languages.*.options.*.rationale' => ['nullable', 'string'],
        ], [
            'masters.*.status.in' => 'An import never sets a status other than draft on a master.',
            'languages.*.locale.in' => 'Locale must be one of pt-BR, es-419, uk-UA, ru-UA (en-US rows are masters).',
            'languages.*.language_status.in' => 'language_status must be draft, in_language_review or approved. Stale is set by the system only.',
        ]);

        $validator->after(function ($v) use ($file) {
            foreach ($file['masters'] ?? [] as $i => $row) {
                $options = collect($row['options'] ?? []);
                $keys = $options->filter(fn ($o) => $o['is_key'] ?? false)->keys();
                $targets = $options->filter(fn ($o) => $o['is_target_misconception'] ?? false)->keys();
                $code = $row['item_code'] ?? $i;

                if ($keys->count() !== 1) {
                    $v->errors()->add("masters.{$i}.options", "{$code}: exactly one option must be the key.");
                } elseif (empty($options[$keys[0]]['rationale'])) {
                    $v->errors()->add("masters.{$i}.options", "{$code}: the keyed option needs a rationale.");
                }
                if ($targets->count() > 1 || $targets->intersect($keys)->isNotEmpty()) {
                    $v->errors()->add("masters.{$i}.options", "{$code}: at most one distractor can be the target misconception.");
                }
            }

            $seen = [];
            foreach ($file['languages'] ?? [] as $i => $row) {
                $id = ($row['item_code'] ?? '').'|'.($row['locale'] ?? '').'|v'.($row['version'] ?? '');
                if (isset($seen[$id])) {
                    $v->errors()->add("languages.{$i}", "Duplicate item_code + locale + version: {$id}.");
                }
                $seen[$id] = true;
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function hash(array $row, string $domainCode, string $key, ?string $target): string
    {
        $options = [];
        foreach (self::LABELS as $label) {
            $options[$label] = [$row['options'][$label]['text'], $row['options'][$label]['rationale'] ?? null];
        }

        return $this->digest($domainCode, $row['task_code'] ?? null, $key, $target, $row['source_standard'] ?? null, $row['stem'], $options);
    }

    /** The same digest, computed from what is stored for the master. */
    private function storedHash(Item $item): string
    {
        $text = $item->translations()->where('locale', config('examprep.fallback_locale'))->orderByDesc('version')->first();
        $key = $item->getAttribute('key');

        $options = [];
        foreach (self::LABELS as $label) {
            $options[$label] = [
                $text?->options[$label] ?? null,
                $label === $key ? $text?->rationale : ($text?->option_rationales[$label] ?? null),
            ];
        }

        return $this->digest($item->domain->code, $item->task?->code, $key, $item->target_misconception, $item->source_ref, $text?->stem, $options);
    }

    private function digest(string $domain, ?string $task, string $key, ?string $target, ?string $source, ?string $stem, array $options): string
    {
        return hash('sha256', json_encode([
            'domain' => $domain,
            'task_code' => $task,
            'key' => $key,
            'target_misconception' => $target,
            'source_standard' => $source,
            'stem' => $stem,
            'options' => $options,
        ], JSON_UNESCAPED_UNICODE));
    }
}

final class DryRunRollback extends RuntimeException {}
