<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['code', 'domain_id', 'task_id', 'version', 'status', 'key', 'source_ref', 'content_hash'];

    /** The key is never serialized. Grading reads it server-side only. */
    protected $hidden = ['key', 'content_hash'];

    protected function casts(): array
    {
        return ['status' => ItemStatus::class, 'version' => 'integer'];
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

    /** Translation for the locale, falling back to English. Second value: is it a fallback? */
    public function contentFor(string $locale): array
    {
        $byLocale = $this->translations->keyBy('locale');
        $hit = $byLocale->get($locale);

        return $hit
            ? [$hit, false]
            : [$byLocale->get(config('examprep.fallback_locale')), true];
    }
}
