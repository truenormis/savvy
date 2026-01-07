<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAll(): Collection
    {
        return User::orderBy('name')->get();
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        if (!isset($data['role'])) {
            $data['role'] = UserRole::ReadOnly;
        }

        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Prevent demoting the last admin
        $newRole = $data['role'] ?? null;
        if ($newRole && $newRole !== UserRole::Admin->value && $user->isAdmin()) {
            $adminCount = User::where('role', UserRole::Admin)->count();
            if ($adminCount <= 1) {
                throw new DomainException('Cannot demote the last admin.');
            }
        }

        $user->update($data);

        return $user;
    }

    public function delete(User $user, int $currentUserId): void
    {
        if ($user->id === $currentUserId) {
            throw new DomainException('Cannot delete yourself.');
        }

        // Prevent deleting the last admin
        if ($user->isAdmin()) {
            $adminCount = User::where('role', UserRole::Admin)->count();
            if ($adminCount <= 1) {
                throw new DomainException('Cannot delete the last admin.');
            }
        }

        $user->delete();
    }
}
