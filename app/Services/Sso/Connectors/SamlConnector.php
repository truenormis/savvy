<?php

namespace App\Services\Sso\Connectors;

use App\DTOs\NormalizedIdentity;
use App\Enums\SsoProtocol;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OneLogin\Saml2\Auth as Saml2Auth;
use OneLogin\Saml2\Settings as Saml2Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SamlConnector extends AbstractConnector
{
    public function protocol(): SsoProtocol
    {
        return SsoProtocol::Saml;
    }

    public function redirect(IdentityProvider $provider, ?string $redirectAfter): RedirectResponse
    {
        $state = $this->states->issue($provider, [
            'redirect_after' => $this->sanitizeRedirectAfter($redirectAfter),
        ]);

        $auth = new Saml2Auth($this->buildSettings($provider));

        // $stay=true returns the redirect URL instead of exiting; RelayState
        // carries our state token back to the ACS endpoint.
        $url = $auth->login($state, [], false, false, true);

        $this->states->attachSamlRequestId($state, $auth->getLastRequestID());

        return redirect()->away($url);
    }

    public function handleCallback(IdentityProvider $provider, Request $request): NormalizedIdentity
    {
        $state = (string) $request->input('RelayState');
        $stateRow = $this->states->consume($state);

        if (! $stateRow || $stateRow->identity_provider_id !== $provider->id) {
            throw SsoException::make('invalid_state', 'SSO state is invalid or expired. Please try again.');
        }

        $auth = new Saml2Auth($this->buildSettings($provider));

        try {
            // Passing the original request ID enforces InResponseTo replay checks.
            $auth->processResponse($stateRow->saml_request_id);
        } catch (\Throwable $e) {
            Log::warning('SAML response processing error', ['provider' => $provider->slug, 'error' => $e->getMessage()]);
            throw SsoException::make('saml_invalid', 'Failed to process the SAML response.', 401);
        }

        if (! empty($auth->getErrors()) || ! $auth->isAuthenticated()) {
            Log::warning('SAML assertion rejected', [
                'provider' => $provider->slug,
                'errors' => $auth->getErrors(),
                'reason' => $auth->getLastErrorReason(),
            ]);
            throw SsoException::make('saml_invalid', 'SAML assertion is invalid.', 401);
        }

        return $this->buildIdentity($provider, $auth->getNameId(), $auth->getAttributes());
    }

    public function metadata(IdentityProvider $provider): string
    {
        $settings = new Saml2Settings($this->buildSettings($provider), true);
        $metadata = $settings->getSPMetadata();

        $errors = $settings->validateMetadata($metadata);

        if (! empty($errors)) {
            throw SsoException::make('saml_metadata_invalid', 'Invalid SP metadata: '.implode(', ', $errors), 500);
        }

        return $metadata;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function buildIdentity(IdentityProvider $provider, ?string $nameId, array $attributes): NormalizedIdentity
    {
        $mappings = $provider->claim_mappings
            ?: $this->presets->get($provider->preset)->defaultClaimMappings();

        $first = fn (?string $attr) => $attr ? (data_get($attributes, $attr.'.0') ?? data_get($attributes, $attr)) : null;

        $subject = $first($mappings['subject'] ?? null) ?: $nameId;

        if (blank($subject)) {
            throw SsoException::make('missing_subject', 'SAML assertion has no usable subject.', 422);
        }

        $groupsAttr = $mappings['groups'] ?? null;

        return new NormalizedIdentity(
            subject: (string) $subject,
            email: $first($mappings['email'] ?? null),
            // A signed SAML assertion is authoritative; the IdP vouches for the address.
            emailVerified: true,
            name: $first($mappings['name'] ?? null),
            groups: $groupsAttr ? array_values((array) data_get($attributes, $groupsAttr, [])) : [],
            raw: $attributes,
        );
    }

    private function buildSettings(IdentityProvider $provider): array
    {
        $conn = $this->connection($provider);
        $saml = config('sso.saml');
        $spEntityId = $saml['sp_entity_id'] ?: config('app.url');
        $hasSpKey = filled($saml['sp_private_key']) && filled($saml['sp_x509_cert']);

        $idp = [
            'entityId' => $conn['idp_entity_id'],
            'singleSignOnService' => [
                'url' => $conn['idp_sso_url'],
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
        ];

        $certs = $conn['idp_x509_certs'] ?? [];
        if (count($certs) > 1) {
            $idp['x509certMulti'] = ['signing' => $certs];
        } else {
            $idp['x509cert'] = $certs[0] ?? '';
        }

        return [
            'strict' => true,
            'sp' => [
                'entityId' => $spEntityId,
                'assertionConsumerService' => [
                    'url' => $this->acsUrl($provider),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'x509cert' => $saml['sp_x509_cert'] ?? '',
                'privateKey' => $saml['sp_private_key'] ?? '',
            ],
            'idp' => $idp,
            'security' => [
                'authnRequestsSigned' => $hasSpKey,
                'wantAssertionsSigned' => true,
                'wantMessagesSigned' => false,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'requestedAuthnContext' => false,
            ],
        ];
    }
}
