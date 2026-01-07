<?php

namespace App\Services;

use App\Models\User;
use App\Models\TwoFactorRecoveryCode;
use Illuminate\Support\Facades\Hash;
use OTPHP\TOTP;

class TwoFactorService
{
    private const ISSUER = 'Savvy';
    private const RECOVERY_CODE_COUNT = 8;
    // Unambiguous alphanumeric characters (no 0, 1, i, l, o to avoid confusion)
    private const RECOVERY_CODE_CHARS = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * Enable 2FA for user - generates secret and returns QR code data
     */
    public function enable(User $user): array
    {
        $totp = TOTP::generate();
        $totp->setLabel($user->email);
        $totp->setIssuer(self::ISSUER);

        $secret = $totp->getSecret();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => true,
            'two_factor_confirmed' => false,
        ]);

        return [
            'secret' => $secret,
            'qr_code_url' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * Confirm 2FA setup with a valid code
     * Returns recovery codes on success, null on failure
     */
    public function confirm(User $user, string $code): ?array
    {
        if (!$user->two_factor_enabled || $user->two_factor_confirmed) {
            return null;
        }

        if (!$this->verify($user, $code)) {
            return null;
        }

        $user->update(['two_factor_confirmed' => true]);

        // Generate recovery codes on first confirmation and return them
        return $this->generateRecoveryCodes($user);
    }

    /**
     * Disable 2FA with code verification
     */
    public function disable(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return false;
        }

        // Verify with TOTP code or recovery code
        if (!$this->verify($user, $code) && !$this->verifyRecoveryCode($user, $code)) {
            return false;
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed' => false,
        ]);

        // Delete all recovery codes
        $user->twoFactorRecoveryCodes()->delete();

        return true;
    }

    /**
     * Verify TOTP code
     */
    public function verify(User $user, string $code): bool
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        try {
            $secret = decrypt($user->two_factor_secret);
            $totp = TOTP::createFromSecret($secret);

            return $totp->verify($code);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalizedCode = strtolower(trim($code));

        $recoveryCodes = $user->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->get();

        foreach ($recoveryCodes as $recoveryCode) {
            if (Hash::check($normalizedCode, $recoveryCode->code)) {
                $recoveryCode->markAsUsed();
                return true;
            }
        }

        return false;
    }

    /**
     * Regenerate recovery codes with verification
     */
    public function regenerateRecoveryCodes(User $user, string $code): ?array
    {
        if (!$user->hasTwoFactorEnabled()) {
            return null;
        }

        // Require TOTP code verification
        if (!$this->verify($user, $code)) {
            return null;
        }

        // Mark old codes as used (disable them)
        $user->twoFactorRecoveryCodes()->update(['used_at' => now()]);

        return $this->generateRecoveryCodes($user);
    }

    /**
     * Generate new recovery codes
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plainCode = $this->generateRecoveryCode();
            $codes[] = $plainCode;

            TwoFactorRecoveryCode::create([
                'user_id' => $user->id,
                'code' => Hash::make($plainCode),
            ]);
        }

        return $codes;
    }

    /**
     * Generate a single recovery code in format: a]v4-x82k
     */
    private function generateRecoveryCode(): string
    {
        $chars = self::RECOVERY_CODE_CHARS;
        $length = strlen($chars);

        $part1 = '';
        $part2 = '';

        for ($i = 0; $i < 4; $i++) {
            $part1 .= $chars[random_int(0, $length - 1)];
            $part2 .= $chars[random_int(0, $length - 1)];
        }

        return $part1 . '-' . $part2;
    }

    /**
     * Get remaining recovery codes count
     */
    public function getRemainingRecoveryCodesCount(User $user): int
    {
        return $user->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->count();
    }

    /**
     * Check if user needs to set up 2FA (enabled but not confirmed)
     */
    public function needsConfirmation(User $user): bool
    {
        return $user->two_factor_enabled && !$user->two_factor_confirmed;
    }
}
