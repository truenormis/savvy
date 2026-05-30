<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoLoginTicket extends Model
{
    protected $fillable = [
        'ticket',
        'user_id',
        'requires_2fa',
        'two_factor_token',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_2fa' => 'boolean',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
