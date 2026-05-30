<?php

namespace App\Services\Sso\Connectors;

use App\DTOs\NormalizedIdentity;
use App\Enums\SsoProtocol;
use App\Models\IdentityProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface SsoConnector
{
    /**
     * The protocol this connector handles. Used to index the ConnectorFactory.
     */
    public function protocol(): SsoProtocol;

    /**
     * Begin the login: persist transient state and redirect to the IdP.
     */
    public function redirect(IdentityProvider $provider, ?string $redirectAfter): RedirectResponse;

    /**
     * Validate the IdP response and return the normalized external identity.
     */
    public function handleCallback(IdentityProvider $provider, Request $request): NormalizedIdentity;
}
