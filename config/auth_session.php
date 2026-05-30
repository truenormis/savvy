<?php

return [

    // Base name of the httpOnly cookie carrying the opaque session token.
    // On HTTPS it is prefixed with __Host- (browser-enforced hardening).
    'cookie' => env('AUTH_SESSION_COOKIE', 'svy_session'),

    // Readable companion cookie holding the CSRF token (double-submit).
    'csrf_cookie' => env('AUTH_SESSION_CSRF_COOKIE', 'svy_csrf'),

    // Header the SPA echoes the CSRF token back in.
    'csrf_header' => 'X-CSRF-Token',

    // Idle timeout (minutes): a session dies this long after its last use.
    'idle_ttl' => (int) env('AUTH_SESSION_IDLE_TTL', 60 * 24),

    // Absolute lifetime (minutes): hard cap regardless of activity.
    'absolute_ttl' => (int) env('AUTH_SESSION_ABSOLUTE_TTL', 60 * 24 * 30),

    // Pre-auth 2FA challenge lifetime (minutes).
    'challenge_ttl' => (int) env('AUTH_SESSION_CHALLENGE_TTL', 5),

    // Rotate the session token after this many minutes of life (mitigates fixation/theft).
    'rotate_after' => (int) env('AUTH_SESSION_ROTATE_AFTER', 60),
];
