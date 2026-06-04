<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Concerns\IssuesAuthSession;
use App\Models\AuthSession;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Services\Auth\AuthCookies;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use IssuesAuthSession;

    public function __construct(
        private AuthSessionService $sessions,
        private TwoFactorChallengeService $challenges,
        private AuthCookies $cookies,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'needs_registration' => User::count() === 0,
            'password_login_enabled' => ! $this->passwordLoginDisabled(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        if (User::count() > 0) {
            return response()->json(['message' => 'Registration is closed.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::Admin,
        ]);

        return $this->issueSession($user, $request, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($this->passwordLoginDisabled()) {
            throw ValidationException::withMessages([
                'email' => ['Password sign-in is disabled. Use single sign-on instead.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->is_sso_only || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'requires_2fa' => true,
                'two_factor_token' => $this->challenges->issue($user),
            ]);
        }

        return $this->issueSession($user, $request);
    }

    /**
     * Password sign-in is blocked only while an enabled SSO provider exists, so
     * disabling SSO (or having none) can never lock the instance out.
     */
    private function passwordLoginDisabled(): bool
    {
        return ! settings('password_login_enabled', true)
            && IdentityProvider::where('enabled', true)->exists();
    }

    public function me(Request $request): JsonResponse
    {
        $token = $this->cookies->readToken($request);
        $session = $token ? $this->sessions->resolve($token) : null;

        return response()->json([
            'user' => $session?->user ? $this->sessionUser($session->user) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('auth_session');

        if ($session instanceof AuthSession) {
            $this->sessions->revoke($session);
        }

        $response = response()->json(['message' => 'Logged out.']);

        foreach ($this->cookies->forget($request) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
