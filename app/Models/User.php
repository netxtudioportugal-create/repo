<?php

namespace App\Models;

use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Staff member (admin, reviewer, tester). Candidates are Learners, not Users. */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => StaffRole::class,
        ];
    }

    public function isReadOnly(): bool
    {
        return $this->role === StaffRole::Viewer;
    }

    /**
     * May this account take an action that needs $role (null: any acting staff)?
     * A read-only account can never act, whatever the role asked for.
     */
    public function hasRole(?string $role): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }

        if ($role === null) {
            return true;
        }

        return $this->role === StaffRole::Admin || $this->role->value === $role;
    }
}
