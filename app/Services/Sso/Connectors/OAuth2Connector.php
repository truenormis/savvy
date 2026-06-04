<?php

namespace App\Services\Sso\Connectors;

use App\DTOs\NormalizedIdentity;
use App\Enums\SsoProtocol;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OAuth2Connector extends AbstractConnector
{
    public function protocol(): SsoProtocol
    {
        return SsoProtocol::OAuth2;
    }

    public function redirect(IdentityProvider $provider, ?string $redirectAfter): RedirectResponse
    {
        $conn = $this->connection($provider);

        $state = $this->states->issue($provider, [
            'redirect_after' => $this->sanitizeRedirectAfter($redirectAfter),
        ]);

        $url = $conn['authorize_url'].'?'.http_build_query([
            'client_id' => $conn['client_id'],
            'redirect_uri' => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $conn['scopes']),
            'state' => $state,
        ]);

        return redirect()->away($url);
    }

    public function handleCallback(IdentityProvider $provider, Request $request): NormalizedIdentity
    {
        if ($request->filled('error')) {
            throw SsoException::make('idp_error', (string) $request->input('error_description', $request->input('error')));
        }

        $code = (string) $request->input('code');
        $state = (string) $request->input('state');

        if (blank($code) || blank($state)) {
            throw SsoException::make('invalid_request', 'Missing authorization code or state.');
        }

        $stateRow = $this->states->consume($state);

        if (! $stateRow || $stateRow->identity_provider_id !== $provider->id) {
            throw SsoException::make('invalid_state', 'SSO state is invalid or expired. Please try again.');
        }

        $conn = $this->connection($provider);
        $accessToken = $this->exchangeCode($provider, $conn, $code);
        $claims = $this->fetchUserinfo($conn['userinfo_url'], $accessToken);

        $overrides = $this->resolveVerifiedEmail($conn, $accessToken, $claims);

        return $this->extractIdentity($provider, $claims, $overrides);
    }

    private function exchangeCode(IdentityProvider $provider, array $conn, string $code): string
    {
        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post($conn['token_url'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl($provider),
                'client_id' => $conn['client_id'],
                'client_secret' => $provider->secret('client_secret'),
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            Log::warning('OAuth2 token exchange failed', [
                'provider' => $provider->slug,
                'status' => $response->status(),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
            ]);
            throw SsoException::make('token_exchange_failed', 'Failed to exchange authorization code.', 502);
        }

        return (string) $response->json('access_token');
    }

    private function fetchUserinfo(string $url, string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['Accept' => 'application/json', 'User-Agent' => config('app.name', 'Savvy')])
            ->timeout(10)
            ->get($url);

        if (! $response->successful()) {
            throw SsoException::make('userinfo_failed', 'Failed to fetch user profile from provider.', 502);
        }

        return (array) $response->json();
    }

    /**
     * Resolve a verified primary email out-of-band (GitHub hides emails on the
     * profile and reports verification only via /user/emails).
     *
     * @return array<string, mixed>
     */
    private function resolveVerifiedEmail(array $conn, string $accessToken, array $claims): array
    {
        $emailsUrl = $conn['emails_url'] ?? null;

        if (! $emailsUrl) {
            return [];
        }

        // A dedicated emails endpoint (GitHub) makes the profile email
        // untrustworthy: only a primary, verified address counts, and nothing
        // may stand in for it — otherwise a blank email forces a hard block.
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => config('app.name', 'Savvy')])
                ->timeout(10)
                ->get($emailsUrl);

            if ($response->successful()) {
                foreach ((array) $response->json() as $entry) {
                    if (($entry['primary'] ?? false) && ($entry['verified'] ?? false)) {
                        return ['email' => $entry['email'], 'email_verified' => true];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('OAuth2 email resolution skipped', ['error' => $e->getMessage()]);
        }

        return ['email' => null, 'email_verified' => false];
    }
}
