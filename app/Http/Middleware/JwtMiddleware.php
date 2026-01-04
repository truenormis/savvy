<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(
        private JwtService $jwt
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getToken($request);

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userId = $this->jwt->getUserId($token);

        if (!$userId) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function getToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
