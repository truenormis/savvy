<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebauthnCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'name',
        'aaguid',
        'record',
        'transports',
        'counter',
        'last_used_at',
    ];

    protected $hidden = [
        'record',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'counter' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
