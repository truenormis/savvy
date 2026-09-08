<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookies
{
    /**
     * On a secure origin the session cookie carries the __Host- prefix, which
     * the browser hard-enforces (HTTPS only, Path=/, no Domain — uncapturable
     * from sibling hosts). On plain HTTP the prefix is dropped so self-hosted
     * LAN/Pi deployments keep working.
     */
    public static function sessionName(bool $secure): string
    {
        $base = config('auth_session.cookie');

        return $secure ? '__Host-'.$base : $base;
    }

    public function readToken(Request $request): ?string
    {
        return $request->cookie(self::sessionName(true)) ?? $request->cookie(self::sessionName(false));
    }

    /**
     * @return array<int, Cookie>
     */
    public function make(Request $request, string $token, string $csrf): array
    {
        $minutes = (int) config('auth_session.absolute_ttl');
        $secure = $request->isSecure();

        return [
            $this->cookie(self::sessionName($secure), $token, $minutes, $secure, httpOnly: true),
            $this->cookie(config('auth_session.csrf_cookie'), $csrf, $minutes, $secure, httpOnly: false),
        ];
    }

    /**
     * @return array<int, Cookie>
     */
    public function forget(Request $request): array
    {
        $secure = $request->isSecure();

        return [
            $this->cookie(self::sessionName(true), '', -2628000, $secure, httpOnly: true),
            $this->cookie(self::sessionName(false), '', -2628000, false, httpOnly: true),
            $this->cookie(config('auth_session.csrf_cookie'), '', -2628000, $secure, httpOnly: false),
        ];
    }

    private function cookie(string $name, string $value, int $minutes, bool $secure, bool $httpOnly): Cookie
    {
        return new Cookie(
            name: $name,
            value: $value,
            expire: $minutes === 0 ? 0 : now()->addMinutes($minutes)->getTimestamp(),
            path: '/',
            domain: null,
            secure: $secure,
            httpOnly: $httpOnly,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
