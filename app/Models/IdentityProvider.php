<?php

namespace App\Models;

use App\Enums\SsoPreset;
use App\Enums\SsoProtocol;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class IdentityProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'protocol',
        'preset',
        'enabled',
        'sort_order',
        'config',
        'secrets',
        'claim_mappings',
        'role_mapping',
        'default_role',
        'allow_jit',
        'sync_role_on_login',
        'link_by_email',
    ];

    protected $hidden = [
        'secrets',
    ];

    protected function casts(): array
    {
        return [
            'protocol' => SsoProtocol::class,
            'preset' => SsoPreset::class,
            'default_role' => UserRole::class,
            'enabled' => 'boolean',
            'allow_jit' => 'boolean',
            'sync_role_on_login' => 'boolean',
            'link_by_email' => 'boolean',
            'config' => 'array',
            'secrets' => 'encrypted:array',
            'claim_mappings' => 'array',
            'role_mapping' => 'array',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config ?? [], $key, $default);
    }

    public function secret(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->secrets ?? [], $key, $default);
    }

    public function claimPath(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->claim_mappings ?? [], $key, $default);
    }

    public function hasClientSecret(): bool
    {
        return filled($this->secret('client_secret'));
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true)->orderBy('sort_order')->orderBy('id');
    }
}
