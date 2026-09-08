<?php

use App\Enums\UserRole;
use App\Models\IdentityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function selfSignedCert(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'idp.example'], $key);
    $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($x509, $pem);

    return $pem;
}

function samlProvider(array $overrides = []): IdentityProvider
{
    return IdentityProvider::create(array_merge([
        'name' => 'Corp SAML',
        'slug' => 'saml',
        'protocol' => 'saml',
        'preset' => 'custom_saml',
        'enabled' => true,
        'config' => [
            'idp_entity_id' => 'https://idp.example/saml',
            'idp_sso_url' => 'https://idp.example/saml/sso',
            'idp_x509_cert' => selfSignedCert(),
        ],
    ], $overrides));
}

it('serves SP metadata as XML', function () {
    samlProvider();

    $response = $this->get('/api/auth/sso/saml/metadata');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('xml');
    expect($response->getContent())->toContain('EntityDescriptor');
    expect($response->getContent())->toContain('AssertionConsumerService');
});

it('redirects to the IdP with a SAMLRequest', function () {
    samlProvider();

    $response = $this->get('/api/auth/sso/saml/redirect');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://idp.example/saml/sso');
    expect($location)->toContain('SAMLRequest=');
});

it('stores an AuthnRequest id for InResponseTo replay protection', function () {
    samlProvider();

    $state = ssoStartFlow('saml');

    expect($state->saml_request_id)->not->toBeNull();
});

it('blocks the redirect for a disabled SAML provider', function () {
    samlProvider(['enabled' => false]);

    expect(ssoLocationParam($this->get('/api/auth/sso/saml/redirect'), 'sso_error'))->not->toBeNull();
});

it('rejects an ACS post without a valid SAML response', function () {
    samlProvider();
    $state = ssoStartFlow('saml');

    $response = $this->post('/api/auth/sso/saml/acs', [
        'RelayState' => $state->state,
        'SAMLResponse' => base64_encode('<not-a-real-assertion/>'),
    ]);

    expect(ssoLocationParam($response, 'error'))->not->toBeNull();
});

it('rejects an ACS post with an unknown RelayState', function () {
    samlProvider();

    $response = $this->post('/api/auth/sso/saml/acs', [
        'RelayState' => 'bogus',
        'SAMLResponse' => base64_encode('<x/>'),
    ]);

    expect(ssoLocationParam($response, 'error'))->not->toBeNull();
});

it('passes the connection test for a well-formed SAML provider', function () {
    $provider = samlProvider();

    callAs('POST', "/api/identity-providers/{$provider->id}/test", [], ssoUser(UserRole::Admin))
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});
