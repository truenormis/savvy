<?php

namespace App\Services\Sso;

use App\Exceptions\SsoException;
use App\Models\IdentityProvider;

class ProviderResolver
{
    public function enabled(string $slug): IdentityProvider
    {
        return IdentityProvider::where('slug', $slug)->where('enabled', true)->first()
            ?? throw SsoException::make('provider_not_found', 'Unknown or disabled identity provider.', 404);
    }
}
