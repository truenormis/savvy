<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthSession extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'csrf',
        'ip',
        'user_agent',
        'last_used_at',
        'idle_expires_at',
        'absolute_expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
        'csrf',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'idle_expires_at' => 'datetime',
            'absolute_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && now()->lessThan($this->idle_expires_at)
            && now()->lessThan($this->absolute_expires_at);
    }
}
