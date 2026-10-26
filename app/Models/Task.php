<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Internal task label. Never the official BACB task wording (IP hold). */
class Task extends Model
{
    protected $fillable = ['domain_id', 'code', 'labels'];

    protected function casts(): array
    {
        return ['labels' => 'array'];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->labels[$locale] ?? $this->labels[config('examprep.fallback_locale')] ?? $this->code;
    }
}
