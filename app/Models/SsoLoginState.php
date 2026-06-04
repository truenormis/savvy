<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoLoginState extends Model
{
    protected $fillable = [
        'state',
        'identity_provider_id',
        'nonce',
        'code_verifier',
        'saml_request_id',
        'redirect_after',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }
}
