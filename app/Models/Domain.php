<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $fillable = ['code', 'names', 'sort'];

    protected function casts(): array
    {
        return ['names' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->names[$locale] ?? $this->names[config('examprep.fallback_locale')] ?? $this->code;
    }
}
