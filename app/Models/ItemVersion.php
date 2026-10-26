<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['item_id', 'version', 'content_hash', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
