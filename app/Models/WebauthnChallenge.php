<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebauthnChallenge extends Model
{
    public const TYPE_REGISTRATION = 'registration';

    public const TYPE_AUTHENTICATION = 'authentication';

    protected $fillable = [
        'user_id',
        'token_hash',
        'type',
        'options',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
