<?php

namespace App\Http\Concerns;

use App\Models\User;
use App\Services\Auth\AuthCookies;
use App\Services\Auth\AuthSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait IssuesAuthSession
{
    protected function issueSession(User $user, Request $request, int $status = 200): JsonResponse
    {
        $issued = app(AuthSessionService::class)->issue($user, $request);

        $response = response()->json(['user' => $this->sessionUser($user)], $status);

        foreach (app(AuthCookies::class)->make($request, $issued['token'], $issued['csrf']) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    protected function sessionUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
