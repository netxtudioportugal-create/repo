<?php

namespace App\Models;

use App\Enums\LanguageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One language record: item + locale + version (natural key).
 *
 * en-US holds the master text (version 1, updated with the master, no
 * language status). Other locales record the master version they were made
 * from; when the master moves past it the record turns stale. A new version
 * of a language record is a new row, and older rows stay as history.
 */
class ItemTranslation extends Model
{
    protected $fillable = [
        'item_id', 'locale', 'version', 'source_master_version', 'stem', 'options', 'rationale', 'option_rationales',
        'language_status', 'language_reviewer', 'language_reviewed_on', 'language_notes', 'status_set_by', 'import_sequence',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'option_rationales' => 'array',
            'version' => 'integer',
            'source_master_version' => 'integer',
            'language_status' => LanguageStatus::class,
            'language_reviewed_on' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function statusSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_set_by');
    }

    public function isStale(): bool
    {
        return $this->language_status === LanguageStatus::Stale;
    }
}
