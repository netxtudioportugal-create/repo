<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One approved localized term for one ABA concept key, in one dictionary
 * version. Rows are written only by App\Services\TerminologyImporter.
 */
class TerminologyTerm extends Model
{
    protected $fillable = [
        'dictionary_id', 'dictionary_version', 'term_key', 'source_term_en', 'locale',
        'approved_term', 'status', 'effective_on', 'reviewer', 'notes',
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

    /**
     * Approved term for a concept in a locale. Accepts app locales ("uk") and
     * dictionary locales ("uk-UA"). Falls back to the English source term,
     * which is the canonical concept label.
     */
    public static function lookup(string $termKey, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $locale = config("examprep.locales.{$locale}.html", $locale);

        $row = static::current()->where('term_key', $termKey)->where('locale', $locale)->first()
            ?? static::current()->where('term_key', $termKey)->first();

        if (! $row) {
            return null;
        }

        return $row->locale === $locale ? $row->approved_term : $row->source_term_en;
    }
}
