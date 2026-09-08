<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    protected $table = 'user_identities';

    protected $fillable = [
        'user_id',
        'identity_provider_id',
        'subject',
        'last_login_at',
        'claims',
    ];

    protected $hidden = [
        'claims',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'claims' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class);
    }
}
