<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwt
    ) {}

    /**
     * Check if registration is needed (no users exist)
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'needs_registration' => User::count() === 0,
        ]);
    }

    /**
     * Register the first user (only when no users exist)
     */
    public function register(Request $request): JsonResponse
    {
        // Only allow registration if no users exist
        if (User::count() > 0) {
            return response()->json([
                'message' => 'Registration is closed.',
            ], 403);
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

        return response()->json([
            'user' => $this->userResponse($user),
            'token' => $this->jwt->encode($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Check if 2FA is enabled
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'requires_2fa' => true,
                'two_factor_token' => $this->jwt->encodeTwoFactorToken($user),
            ]);
        }

        return response()->json([
            'user' => $this->userResponse($user),
            'token' => $this->jwt->encode($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userResponse($request->user()),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        return response()->json([
            'token' => $this->jwt->encode($request->user()),
        ]);
    }

    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
