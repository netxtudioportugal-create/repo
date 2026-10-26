<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Models\Domain;
use App\Models\Item;
use App\Models\ItemStatusChange;
use App\Models\ItemVersion;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Transactional item import. The JSON bank in the repository is the source of
 * truth for item content; review status lives in the database.
 *
 * - New item: created at version 1, status draft.
 * - Changed content (stem, options, key, rationales, task, source): version
 *   bumps, a snapshot is stored, and the item goes back to draft, because a
 *   reviewed version is not the version that would be shown.
 * - Unchanged content: left alone, so the import is safe to run on every deploy.
 * - Any invalid item rolls back the whole import.
 */
class ItemImporter
{
    /** @return array{created: list<string>, updated: list<string>, unchanged: list<string>} */
    public function import(array $bank, bool $dryRun = false): array
    {
        $this->validate($bank);

        $report = ['created' => [], 'updated' => [], 'unchanged' => []];

        try {
            DB::transaction(function () use ($bank, $dryRun, &$report) {
                $domains = Domain::pluck('id', 'code');

                foreach ($bank['items'] as $data) {
                    $hash = $this->hash($data);
                    $item = Item::where('code', $data['code'])->lockForUpdate()->first();

                    if ($item && hash_equals($item->content_hash, $hash)) {
                        $report['unchanged'][] = $data['code'];

                        continue;
                    }

                    $taskId = null;
                    if (! empty($data['task_code'])) {
                        $taskId = Task::firstOrCreate(
                            ['code' => $data['task_code']],
                            ['domain_id' => $domains[$data['domain']], 'labels' => ['en' => $data['task_code']]],
                        )->id;
                    }

                    $attributes = [
                        'domain_id' => $domains[$data['domain']],
                        'task_id' => $taskId,
                        'key' => $data['key'],
                        'source_ref' => $data['source_ref'] ?? null,
                        'content_hash' => $hash,
                    ];

                    if (! $item) {
                        $item = Item::create($attributes + [
                            'code' => $data['code'],
                            'version' => 1,
                            'status' => ItemStatus::Draft,
                        ]);
                        $report['created'][] = $data['code'];
                        $from = null;
                    } else {
                        $from = $item->status->value;
                        $item->update($attributes + [
                            'version' => $item->version + 1,
                            'status' => ItemStatus::Draft,
                        ]);
                        $report['updated'][] = $data['code'];
                    }

                    $item->translations()->delete();
                    foreach ($data['translations'] as $locale => $t) {
                        $item->translations()->create([
                            'locale' => $locale,
                            'stem' => $t['stem'],
                            'options' => $t['options'],
                            'rationale' => $t['rationale'],
                            'option_rationales' => $t['option_rationales'] ?? null,
                            'review_status' => $t['review_status'] ?? 'draft',
                        ]);
                    }

                    ItemVersion::create([
                        'item_id' => $item->id,
                        'version' => $item->version,
                        'content_hash' => $hash,
                        'snapshot' => $data,
                    ]);

                    ItemStatusChange::create([
                        'item_id' => $item->id,
                        'item_version' => $item->version,
                        'from_status' => $from,
                        'to_status' => ItemStatus::Draft->value,
                        'note' => $from === null ? 'Imported' : 'Content changed on import; review restarts',
                    ]);
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

    private function validate(array $bank): void
    {
        $domainCodes = Domain::pluck('code')->all();

        $validator = Validator::make($bank, [
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:40', 'distinct'],
            'items.*.domain' => ['required', 'in:'.implode(',', $domainCodes)],
            'items.*.task_code' => ['nullable', 'string', 'max:10'],
            'items.*.key' => ['required', 'in:A,B,C,D'],
            'items.*.source_ref' => ['nullable', 'string', 'max:255'],
            'items.*.translations' => ['required', 'array'],
            'items.*.translations.en' => ['required', 'array'],
            'items.*.translations.*.stem' => ['required', 'string'],
            'items.*.translations.*.options' => ['required', 'array:A,B,C,D', 'size:4'],
            'items.*.translations.*.options.*' => ['required', 'string'],
            'items.*.translations.*.rationale' => ['required', 'string'],
            'items.*.translations.*.option_rationales' => ['nullable', 'array:A,B,C,D'],
        ]);

        $validator->after(function ($v) use ($bank) {
            $locales = array_keys(config('examprep.locales'));
            foreach ($bank['items'] ?? [] as $i => $item) {
                foreach (array_keys($item['translations'] ?? []) as $locale) {
                    if (! in_array($locale, $locales, true)) {
                        $v->errors()->add("items.$i.translations", "Unknown locale [$locale] on {$item['code']}.");
                    }
                }
                if (isset($item['translations']['en']['option_rationales'][$item['key'] ?? ''])) {
                    $v->errors()->add("items.$i.key", "{$item['code']}: the keyed option has a distractor rationale.");
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function hash(array $data): string
    {
        $content = [
            'domain' => $data['domain'],
            'task_code' => $data['task_code'] ?? null,
            'key' => $data['key'],
            'source_ref' => $data['source_ref'] ?? null,
            'translations' => $data['translations'],
        ];
        ksort($content['translations']);

        return hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE));
    }
}

final class DryRunRollback extends RuntimeException {}
