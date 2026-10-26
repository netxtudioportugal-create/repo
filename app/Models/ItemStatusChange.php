<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['item_id', 'user_id', 'item_version', 'from_status', 'to_status', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
