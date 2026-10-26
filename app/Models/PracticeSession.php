<?php

namespace App\Models;

use App\Enums\SessionMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSession extends Model
{
    use HasUuids;

    protected $fillable = ['learner_id', 'mode', 'domain_id', 'is_staff_test', 'started_at', 'ends_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'mode' => SessionMode::class,
            'is_staff_test' => 'boolean',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('position');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && now()->greaterThanOrEqualTo($this->ends_at);
    }
}
