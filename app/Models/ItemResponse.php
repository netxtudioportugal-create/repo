<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of the append-only responses table. */
class ItemResponse extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'responses';

    protected $fillable = [
        'learner_id', 'practice_session_id', 'item_id', 'item_version', 'selected_option',
        'is_correct', 'option_order_key', 'response_time_ms', 'is_staff_test',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'is_staff_test' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
