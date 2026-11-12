<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One controlled term for one ABA concept key, in one locale and dictionary
 * version. Rows are written only by App\Services\TerminologyImporter.
 */
class TerminologyTerm extends Model
{
    protected $fillable = [
        'dictionary_id', 'dictionary_version', 'term_key', 'source_term_en', 'locale',
        'localized_term', 'status', 'review_status', 'reviewer', 'approved_by',
        'effective_on', 'notes', 'source_package',
    ];

    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    /** Rows of the newest dictionary version on record. */
    public function scopeCurrent(Builder $query): Builder
    {
        $versions = static::query()->distinct()->pluck('dictionary_version')->all();
        usort($versions, 'version_compare');

        return $query->where('dictionary_version', end($versions) ?: '');
    }

    /** Usable in released learner-facing content: canonical or approved after review. */
    public function isReleasable(): bool
    {
        return in_array($this->status, ['canonical', 'approved'], true);
    }

    /**
     * Term for a concept in a locale. Falls back to the canonical en-US term.
     */
    public static function lookup(string $termKey, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $rows = static::current()->where('term_key', $termKey)->whereIn('locale', [$locale, 'en-US'])
            ->pluck('localized_term', 'locale');

        return $rows[$locale] ?? $rows['en-US'] ?? null;
    }
}
