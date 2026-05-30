<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthCookies;
use App\Services\Auth\AuthSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSession
{
    public function __construct(
        private AuthSessionService $sessions,
        private AuthCookies $cookies,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->cookies->readToken($request);
        $session = $token ? $this->sessions->resolve($token) : null;

        if (! $session) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn () => $session->user);
        $request->attributes->set('auth_session', $session);

        $this->sessions->touch($session);
        $rotated = $this->sessions->shouldRotate($session) ? $this->sessions->rotate($session) : null;

        $response = $next($request);

        if ($rotated) {
            foreach ($this->cookies->make($request, $rotated['token'], $rotated['csrf']) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }
}
