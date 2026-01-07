<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
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
