<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'practice_session_id', 'item_id', 'item_version', 'position', 'option_order',
        'shown_at', 'selected_option', 'is_correct', 'answered_at', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'shown_at' => 'datetime',
            'answered_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PracticeSession::class, 'practice_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Canonical labels in display order, e.g. ['C','A','B','D']. */
    public function displayOrder(): array
    {
        return str_split($this->option_order);
    }
}
