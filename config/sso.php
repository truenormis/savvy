<?php

use App\Enums\UserRole;

/*
|--------------------------------------------------------------------------
| SSO Configuration
|--------------------------------------------------------------------------
|
| Per-provider presets live in dedicated classes under app/Services/Sso/Presets
| and are resolved through the PresetRegistry. This file only holds global,
| environment-driven knobs. Providers themselves live in the database.
|
*/

return [

    // Clock skew tolerance (seconds) for JWT exp/nbf/iat and SAML conditions.
    'leeway' => (int) env('SSO_LEEWAY', 60),

    // TTLs (seconds).
    'state_ttl' => (int) env('SSO_STATE_TTL', 600),   // authorization request lifetime
    'ticket_ttl' => (int) env('SSO_TICKET_TTL', 120), // handoff ticket lifetime

    // Discovery/JWKS perf cache (seconds).
    'discovery_cache_ttl' => (int) env('SSO_DISCOVERY_CACHE_TTL', 3600),

    /*
    | Shared SAML Service Provider keypair. Used to sign AuthnRequests and SP
    | metadata across all SAML providers. Generate once at install:
    |   php artisan sso:generate-sp-keypair
    | SettingsService does not encrypt, so the private key lives in env.
    */
    'saml' => [
        'sp_entity_id' => env('SAML_SP_ENTITY_ID'),       // defaults to APP_URL when null
        'sp_x509_cert' => env('SAML_SP_CERT'),
        'sp_private_key' => env('SAML_SP_PRIVATE_KEY'),
    ],

    /*
    | default_role values the admin may pick for JIT provisioning. Admin is
    | intentionally excluded — it can only be granted via an explicit
    | role-mapping rule, never as a JIT default.
    */
    'assignable_default_roles' => [
        UserRole::ReadOnly->value,
        UserRole::ReadWrite->value,
    ],
];
