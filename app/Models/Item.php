<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    /**
     * version: content revision, raised by the system whenever the master text
     * changes (sessions and responses point at it). master_version: declared by
     * the import file; language records made from an older one turn stale.
     */
    protected $fillable = [
        'code', 'domain_id', 'task_id', 'version', 'master_version', 'status', 'key', 'target_misconception',
        'source_ref', 'content_hash', 'key_concept', 'question_type', 'difficulty_author', 'authority_source',
        'authority_version', 'source_checked_on', 'is_rule_dependent', 'requires_reverification', 'cadence_months',
        'ip_clearance', 'source_review_hold', 'import_sequence',
    ];

    /** The key is never serialized. Grading reads it server-side only. */
    protected $hidden = ['key', 'content_hash'];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'version' => 'integer',
            'master_version' => 'integer',
            'source_checked_on' => 'date',
            'is_rule_dependent' => 'boolean',
            'requires_reverification' => 'boolean',
            'source_review_hold' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ItemTranslation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ItemVersion::class)->orderByDesc('version');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ItemStatusChange::class)->latest('id');
    }

    /**
     * Candidates only ever see released items. Staff test view sees every
     * item that is not retired, each marked as a draft in the interface.
     */
    public function scopeAvailableTo(Builder $query, bool $staff): Builder
    {
        return $staff
            ? $query->where('status', '!=', ItemStatus::Retired->value)
            : $query->where('status', ItemStatus::Released->value);
    }

    /** Current language record per locale: the highest version. */
    public function currentTranslations(): \Illuminate\Support\Collection
    {
        return $this->translations->sortByDesc('version')->unique('locale')->keyBy('locale');
    }

    /**
     * Content for the locale, falling back to English. Second value: is it a
     * fallback? A stale language record is never shown: its labels and wording
     * may no longer match the master, so English is shown with the pending note.
     */
    public function contentFor(string $locale): array
    {
        $current = $this->currentTranslations();
        $hit = $current->get($locale);

        return $hit && ! $hit->isStale()
            ? [$hit, false]
            : [$current->get(config('examprep.fallback_locale')), true];
    }
}
