<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed' => 'boolean',
        ];
    }

    public function twoFactorRecoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isReadWrite(): bool
    {
        return $this->role === UserRole::ReadWrite;
    }

    public function isReadOnly(): bool
    {
        return $this->role === UserRole::ReadOnly;
    }

    public function canWrite(): bool
    {
        return $this->isAdmin() || $this->isReadWrite();
    }
}
