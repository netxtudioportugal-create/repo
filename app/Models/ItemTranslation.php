<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemTranslation extends Model
{
    protected $fillable = ['item_id', 'locale', 'stem', 'options', 'rationale', 'option_rationales', 'review_status'];

    protected function casts(): array
    {
        return ['options' => 'array', 'option_rationales' => 'array'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
