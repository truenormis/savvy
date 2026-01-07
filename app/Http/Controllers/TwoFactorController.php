<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private JwtService $jwt
    ) {}

    /**
     * Enable 2FA - returns secret and QR code URL
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        $data = $this->twoFactor->enable($user);

        return response()->json([
            'secret' => $data['secret'],
            'qr_code_url' => $data['qr_code_url'],
        ]);
    }

    /**
     * Confirm 2FA setup with TOTP code
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$this->twoFactor->needsConfirmation($user)) {
            return response()->json([
                'message' => 'Two-factor authentication is not pending confirmation.',
            ], 400);
        }

        $recoveryCodes = $this->twoFactor->confirm($user, $request->code);

        if ($recoveryCodes === null) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA with verification
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        if (!$this->twoFactor->disable($user, $request->code)) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    /**
     * Verify 2FA code during login
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'two_factor_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Decode the temporary token to get user ID
        $userId = $this->jwt->getUserId($request->two_factor_token);

        if (!$userId) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 401);
        }

        // Try TOTP code first, then recovery code
        $verified = $this->twoFactor->verify($user, $request->code)
            || $this->twoFactor->verifyRecoveryCode($user, $request->code);

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'user' => $this->userResponse($user),
            'token' => $this->jwt->encode($user),
        ]);
    }

    /**
     * Get recovery codes status
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        return response()->json([
            'remaining_count' => $this->twoFactor->getRemainingRecoveryCodesCount($user),
        ]);
    }

    /**
     * Regenerate recovery codes with TOTP verification
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($user, $request->code);

        if ($codes === null) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Recovery codes have been regenerated.',
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Get 2FA status for current user
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending_confirmation' => $this->twoFactor->needsConfirmation($user),
            'recovery_codes_remaining' => $user->hasTwoFactorEnabled()
                ? $this->twoFactor->getRemainingRecoveryCodesCount($user)
                : null,
        ]);
    }

    private function userResponse($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
