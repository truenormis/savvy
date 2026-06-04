<?php

namespace App\Http\Middleware;

use App\Models\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        $session = $request->attributes->get('auth_session');
        $header = (string) $request->header(config('auth_session.csrf_header'));

        if (! $session instanceof AuthSession || $header === '' || ! hash_equals($session->csrf, $header)) {
            return response()->json(['message' => 'CSRF token mismatch.'], 419);
        }

        return $next($request);
    }
}
