<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pseudonymous candidate identity. Deleting it deletes every session and response (cascade). */
class Learner extends Model
{
    use HasUuids;

    protected $fillable = ['locale', 'acknowledged_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ItemResponse::class);
    }
}
