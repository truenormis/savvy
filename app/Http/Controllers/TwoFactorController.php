<?php

namespace App\Http\Controllers;

use App\Http\Concerns\IssuesAuthSession;
use App\Services\Auth\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    use IssuesAuthSession;

    public function __construct(
        private TwoFactorService $twoFactor,
        private TwoFactorChallengeService $challenges
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

        if (! $this->twoFactor->needsConfirmation($user)) {
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

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        if (! $this->twoFactor->disable($user, $request->code)) {
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

        $user = $this->challenges->resolve($request->two_factor_token);

        if (! $user) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $verified = $this->twoFactor->verify($user, $request->code)
            || $this->twoFactor->verifyRecoveryCode($user, $request->code);

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $this->challenges->consume($request->two_factor_token);

        return $this->issueSession($user, $request);
    }

    /**
     * Get recovery codes status
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
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

        if (! $user->hasTwoFactorEnabled()) {
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
}
