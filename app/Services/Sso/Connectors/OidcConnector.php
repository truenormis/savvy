<?php

namespace App\Services\Sso\Connectors;

use App\DTOs\NormalizedIdentity;
use App\Enums\SsoProtocol;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Services\Sso\JwksService;
use App\Services\Sso\Presets\AbstractOidcPreset;
use App\Services\Sso\Presets\PresetRegistry;
use App\Services\Sso\SsoStateStore;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OidcConnector extends AbstractConnector
{
    public function __construct(
        SsoStateStore $states,
        PresetRegistry $presets,
        private JwksService $jwks,
    ) {
        parent::__construct($states, $presets);
    }

    public function protocol(): SsoProtocol
    {
        return SsoProtocol::Oidc;
    }

    public function redirect(IdentityProvider $provider, ?string $redirectAfter): RedirectResponse
    {
        $conn = $this->connection($provider);
        $disco = $this->jwks->discover($conn['discovery_url']);

        $nonce = Str::random(32);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $state = $this->states->issue($provider, [
            'nonce' => $nonce,
            'code_verifier' => $verifier,
            'redirect_after' => $this->sanitizeRedirectAfter($redirectAfter),
        ]);

        $url = $disco['authorization_endpoint'].'?'.http_build_query([
            'client_id' => $conn['client_id'],
            'redirect_uri' => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $conn['scopes']),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
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
        $disco = $this->jwks->discover($conn['discovery_url']);

        $tokens = $this->exchangeCode($provider, $disco, $conn, $code, $stateRow->code_verifier);

        if (empty($tokens['id_token'])) {
            throw SsoException::make('missing_id_token', 'Identity provider did not return an id_token.', 502);
        }

        $preset = $this->presets->get($provider->preset);
        $claims = $this->verifyIdToken($preset, $disco, $conn['client_id'], $tokens['id_token'], $stateRow->nonce);
        $claims = $this->enrichWithUserinfo($disco, $tokens, $claims);

        return $this->extractIdentity($provider, $claims);
    }

    private function exchangeCode(IdentityProvider $provider, array $disco, array $conn, string $code, ?string $verifier): array
    {
        $response = Http::asForm()
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(10)
            ->post($disco['token_endpoint'], array_filter([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl($provider),
                'client_id' => $conn['client_id'],
                'client_secret' => $provider->secret('client_secret'),
                'code_verifier' => $verifier,
            ], fn ($v) => $v !== null));

        if (! $response->successful()) {
            Log::warning('OIDC token exchange failed', ['provider' => $provider->slug, 'status' => $response->status()]);
            throw SsoException::make('token_exchange_failed', 'Failed to exchange authorization code.', 502);
        }

        return (array) $response->json();
    }

    private function verifyIdToken(AbstractOidcPreset $preset, array $disco, string $clientId, string $idToken, ?string $expectedNonce): array
    {
        JWT::$leeway = (int) config('sso.leeway', 60);
        $jwksUri = $disco['jwks_uri'] ?? throw SsoException::make('no_jwks', 'Discovery document has no jwks_uri.', 502);

        try {
            $payload = JWT::decode($idToken, $this->jwks->keySet($jwksUri));
        } catch (\Throwable $first) {
            // A rotated signing key (unknown kid) — force a single JWKS refresh.
            try {
                $payload = JWT::decode($idToken, $this->jwks->keySet($jwksUri, force: true));
            } catch (\Throwable $second) {
                Log::warning('OIDC id_token verification failed', ['error' => $second->getMessage()]);
                throw SsoException::make('invalid_id_token', 'Could not verify the id_token signature.', 401);
            }
        }

        $claims = json_decode(json_encode($payload), true);

        if (! $preset->matchesIssuer((string) ($disco['issuer'] ?? ''), $claims)) {
            throw SsoException::make('invalid_issuer', 'id_token issuer mismatch.', 401);
        }

        $aud = (array) ($claims['aud'] ?? []);
        if (! in_array($clientId, $aud, true)) {
            throw SsoException::make('invalid_audience', 'id_token audience mismatch.', 401);
        }

        $azp = $claims['azp'] ?? null;
        if ($azp !== null && ! hash_equals($clientId, (string) $azp)) {
            throw SsoException::make('invalid_audience', 'id_token authorized party mismatch.', 401);
        }

        if (count($aud) > 1 && $azp === null) {
            throw SsoException::make('invalid_audience', 'id_token has multiple audiences without an authorized party.', 401);
        }

        if ($expectedNonce !== null && ($claims['nonce'] ?? null) !== $expectedNonce) {
            throw SsoException::make('invalid_nonce', 'id_token nonce mismatch.', 401);
        }

        return $claims;
    }

    private function enrichWithUserinfo(array $disco, array $tokens, array $claims): array
    {
        $endpoint = $disco['userinfo_endpoint'] ?? null;
        $accessToken = $tokens['access_token'] ?? null;

        if (! $endpoint || ! $accessToken) {
            return $claims;
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(10)
                ->get($endpoint);

            if ($response->successful()) {
                // id_token claims take precedence over userinfo.
                return array_merge((array) $response->json(), $claims);
            }
        } catch (\Throwable $e) {
            Log::info('OIDC userinfo fetch skipped', ['error' => $e->getMessage()]);
        }

        return $claims;
    }
}
