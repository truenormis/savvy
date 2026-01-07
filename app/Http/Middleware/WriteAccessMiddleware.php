<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WriteAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isReadOnly() && !$request->isMethod('GET')) {
            return response()->json(['message' => 'Read-only access'], 403);
        }

        return $next($request);
    }
}
