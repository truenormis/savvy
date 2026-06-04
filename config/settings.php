<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | These are the default values for system settings. Values stored in the
    | database will override these defaults.
    |
    */

    'auto_update_currencies' => true,

    // Global kill-switch for JIT provisioning of brand-new users via SSO.
    // When false, SSO can only sign in users that already exist (or are linked).
    'sso_allow_signup' => true,

    // When false, email/password sign-in is rejected and only SSO is allowed.
    // Enforced only while an enabled SSO provider exists, so the instance can
    // never lock itself out.
    'password_login_enabled' => true,

    // When true, JIT sign-up is refused unless the identity provider asserts a
    // verified email. Only relevant while SSO sign-up is allowed.
    'sso_require_verified_email' => false,
];
