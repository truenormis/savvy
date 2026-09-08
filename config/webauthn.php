<?php

return [

    'rp_id' => env('WEBAUTHN_RP_ID', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'Savvy')),

    'secured_rp_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'WEBAUTHN_SECURED_RP_IDS',
        str_starts_with((string) env('APP_URL', 'http://localhost'), 'https://')
            ? ''
            : (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')
    ))))),

    'timeout' => (int) env('WEBAUTHN_TIMEOUT', 60000),

    'challenge_ttl' => (int) env('WEBAUTHN_CHALLENGE_TTL', 5),

    'max_credentials_per_user' => (int) env('WEBAUTHN_MAX_CREDENTIALS', 20),
];
