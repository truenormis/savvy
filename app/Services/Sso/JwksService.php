<?php

namespace App\Services\Sso;

use App\Exceptions\SsoException;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JwksService
{
    /**
     * Fetch (and cache) an OIDC discovery document.
     *
     * @return array<string, mixed>
     */
    public function discover(string $discoveryUrl): array
    {
        $ttl = (int) config('sso.discovery_cache_ttl', 3600);
        $key = 'sso:discovery:'.sha1($discoveryUrl);

        return Cache::remember($key, $ttl, function () use ($discoveryUrl) {
            $doc = $this->fetchJson($discoveryUrl);

            if (! isset($doc['issuer'], $doc['authorization_endpoint'], $doc['token_endpoint'])) {
                throw SsoException::make('discovery_failed', 'Invalid OIDC discovery document.', 502);
            }

            return $doc;
        });
    }

    /**
     * Parsed JWKS keyed by kid. On a kid miss the caller forces a refresh to
     * pick up rotated signing keys.
     *
     * @return array<string, \Firebase\JWT\Key>
     */
    public function keySet(string $jwksUri, bool $force = false): array
    {
        $ttl = (int) config('sso.discovery_cache_ttl', 3600);
        $key = 'sso:jwks:'.sha1($jwksUri);

        if ($force) {
            Cache::forget($key);
        }

        $raw = Cache::remember($key, $ttl, fn () => $this->fetchJson($jwksUri));

        if (empty($raw['keys'])) {
            throw SsoException::make('jwks_failed', 'JWKS endpoint returned no keys.', 502);
        }

        return JWK::parseKeySet($this->normalizeAlgs($raw));
    }

    /**
     * Several IdPs (Entra among them) publish JWKS without the optional "alg"
     * member. firebase/php-jwt requires it, so derive it from the key type —
     * never to a symmetric alg, which keeps RS↔HS key-confusion off the table.
     *
     * @param  array<string, mixed>  $jwks
     * @return array<string, mixed>
     */
    private function normalizeAlgs(array $jwks): array
    {
        foreach ($jwks['keys'] as &$key) {
            if (empty($key['alg']) && is_array($key)) {
                $key['alg'] = $this->defaultAlgFor($key);
            }
        }
        unset($key);

        return $jwks;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function defaultAlgFor(array $key): string
    {
        return match ($key['kty'] ?? null) {
            'EC' => match ($key['crv'] ?? null) {
                'P-384' => 'ES384',
                'P-521' => 'ES512',
                default => 'ES256',
            },
            'OKP' => 'EdDSA',
            default => 'RS256',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJson(string $url): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('SSO metadata fetch failed', ['url' => $url, 'status' => $response->status()]);
                throw SsoException::make('metadata_unreachable', 'Failed to reach identity provider metadata.', 502);
            }

            return (array) $response->json();
        } catch (SsoException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('SSO metadata fetch error', ['url' => $url, 'error' => $e->getMessage()]);
            throw SsoException::make('metadata_unreachable', 'Failed to reach identity provider metadata.', 502);
        }
    }
}
