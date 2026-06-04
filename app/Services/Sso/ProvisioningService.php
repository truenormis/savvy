<?php

namespace App\Services\Sso;

use App\DTOs\NormalizedIdentity;
use App\DTOs\ProvisionResult;
use App\Enums\UserRole;
use App\Exceptions\SsoException;
use App\Models\IdentityProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisioningService
{
    public function resolve(IdentityProvider $provider, NormalizedIdentity $identity): ProvisionResult
    {
        return DB::transaction(function () use ($provider, $identity) {
            $link = UserIdentity::where('identity_provider_id', $provider->id)
                ->where('subject', $identity->subject)
                ->first();

            if ($link) {
                return $this->loginExisting($provider, $identity, $link);
            }

            // Never let SSO seize a fresh instance before a local admin exists.
            if (User::count() === 0) {
                throw SsoException::make('no_admin', 'Complete the initial setup before using SSO.', 403);
            }

            $user = $this->linkByEmail($provider, $identity)
                ?? $this->justInTimeProvision($provider, $identity);

            $this->createLink($provider, $identity, $user);

            return new ProvisionResult($user, $user->hasTwoFactorEnabled());
        });
    }

    private function loginExisting(IdentityProvider $provider, NormalizedIdentity $identity, UserIdentity $link): ProvisionResult
    {
        $user = $link->user;

        if (! $user) {
            throw SsoException::make('user_missing', 'Linked account no longer exists.', 403);
        }

        if ($provider->sync_role_on_login) {
            $this->syncRole($user, $provider, $identity);
        }

        $link->update(['last_login_at' => now(), 'claims' => $identity->raw]);

        return new ProvisionResult($user, $user->hasTwoFactorEnabled());
    }

    private function linkByEmail(IdentityProvider $provider, NormalizedIdentity $identity): ?User
    {
        if (! $provider->link_by_email || blank($identity->email) || ! $identity->emailVerified) {
            return null;
        }

        $user = $this->findUserByEmail($identity->email);

        if (! $user) {
            return null;
        }

        if ($provider->sync_role_on_login) {
            $this->syncRole($user, $provider, $identity);
        }

        return $user;
    }

    private function justInTimeProvision(IdentityProvider $provider, NormalizedIdentity $identity): User
    {
        if (! $provider->allow_jit || ! settings('sso_allow_signup', true)) {
            throw SsoException::make('signup_disabled', 'This account is not provisioned. Contact an administrator.', 403);
        }

        if (blank($identity->email)) {
            throw SsoException::make('no_email', 'Identity provider did not return an email address.', 422);
        }

        if (settings('sso_require_verified_email', false) && ! $identity->emailVerified) {
            throw SsoException::make('email_unverified', 'Your identity provider has not verified this email address.', 422);
        }

        if ($this->findUserByEmail($identity->email)) {
            // Email belongs to a local account but linking is disabled for this provider.
            throw SsoException::make('email_in_use', 'An account with this email already exists.', 409);
        }

        return User::create([
            'name' => $identity->name ?: $identity->email,
            'email' => $identity->email,
            'password' => Str::random(40),
            'is_sso_only' => true,
            'role' => $this->mappedRole($provider, $identity) ?? $provider->default_role ?? UserRole::ReadOnly,
        ]);
    }

    /**
     * Email addresses are case-insensitive; match regardless of the casing the
     * IdP reports so we never duplicate or fail to link an existing account.
     */
    private function findUserByEmail(string $email): ?User
    {
        return User::whereRaw('lower(email) = ?', [Str::lower($email)])->first();
    }

    private function createLink(IdentityProvider $provider, NormalizedIdentity $identity, User $user): void
    {
        UserIdentity::create([
            'user_id' => $user->id,
            'identity_provider_id' => $provider->id,
            'subject' => $identity->subject,
            'last_login_at' => now(),
            'claims' => $identity->raw,
        ]);
    }

    private function syncRole(User $user, IdentityProvider $provider, NormalizedIdentity $identity): void
    {
        $mapped = $this->mappedRole($provider, $identity);

        if (! $mapped || $mapped === $user->role) {
            return;
        }

        // Never strip the last admin via an IdP-driven downgrade.
        if ($user->isAdmin() && $mapped !== UserRole::Admin) {
            if (User::where('role', UserRole::Admin)->count() <= 1) {
                Log::warning('SSO role sync skipped: would demote the last admin', ['user' => $user->id]);

                return;
            }
        }

        $user->update(['role' => $mapped]);
    }

    /**
     * Evaluate the provider's ordered role-mapping rules. Returns the first
     * matching role, or null when nothing matches.
     */
    private function mappedRole(IdentityProvider $provider, NormalizedIdentity $identity): ?UserRole
    {
        foreach ($provider->role_mapping ?? [] as $rule) {
            $claim = $rule['claim'] ?? null;
            $role = $rule['role'] ?? null;

            if (! $claim || ! $role) {
                continue;
            }

            $actual = $claim === 'groups'
                ? $identity->groups
                : data_get($identity->raw, $claim);

            if ($this->ruleMatches($rule['operator'] ?? 'equals', $actual, $rule['value'] ?? null)) {
                $resolved = UserRole::tryFrom($role);

                if ($resolved) {
                    if ($resolved === UserRole::Admin) {
                        Log::info('SSO mapped a user to Admin', ['provider' => $provider->slug, 'rule' => $rule]);
                    }

                    return $resolved;
                }
            }
        }

        return null;
    }

    private function ruleMatches(string $operator, mixed $actual, mixed $value): bool
    {
        return match ($operator) {
            'equals' => is_scalar($actual) && (string) $actual === (string) $value,
            'contains' => is_array($actual)
                ? in_array($value, $actual, true)
                : str_contains((string) $actual, (string) $value),
            'one_of' => is_array($actual)
                ? (bool) array_intersect($actual, (array) $value)
                : in_array($actual, (array) $value, true),
            default => false,
        };
    }
}
