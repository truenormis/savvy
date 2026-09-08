<?php

use App\Enums\UserRole;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actorWithRole(UserRole $role): User
{
    return User::create([
        'name' => 'Actor',
        'email' => $role->value.'-'.uniqid().'@test.com',
        'password' => 'secret1',
        'role' => $role,
    ]);
}

describe('read-only role', function () {
    it('may read', function () {
        callAs('GET', '/api/transactions', [], actorWithRole(UserRole::ReadOnly))->assertOk();
    });

    it('may not write', function () {
        $actor = actorWithRole(UserRole::ReadOnly);

        callAs('POST', '/api/tags', ['name' => 'nope'], $actor)->assertStatus(403);
        callAs('PATCH', '/api/settings', [], $actor)->assertStatus(403);
    });
});

describe('read-write role', function () {
    it('may write ordinary resources', function () {
        callAs('POST', '/api/tags', ['name' => 'allowed'], actorWithRole(UserRole::ReadWrite))
            ->assertCreated();
    });

    it('may not administer users or identity providers', function () {
        $actor = actorWithRole(UserRole::ReadWrite);

        callAs('POST', '/api/users', [
            'name' => 'x', 'email' => 'x@test.com', 'password' => 'password123', 'role' => 'admin',
        ], $actor)->assertStatus(403);

        callAs('POST', '/api/identity-providers', ['name' => 'x'], $actor)->assertStatus(403);
    });

    it('may not touch backups, which hold the whole ledger', function () {
        $actor = actorWithRole(UserRole::ReadWrite);

        $backup = Backup::create(['filename' => 'dump.sqlite', 'size' => 10]);

        callAs('GET', '/api/backups', [], $actor)->assertStatus(403);
        callAs('POST', '/api/backups', [], $actor)->assertStatus(403);
        callAs('POST', "/api/backups/{$backup->id}/restore", [], $actor)->assertStatus(403);
        callAs('GET', "/api/backups/{$backup->id}/download", [], $actor)->assertStatus(403);
        callAs('DELETE', "/api/backups/{$backup->id}", [], $actor)->assertStatus(403);
    });
});

describe('admin role', function () {
    it('may list backups', function () {
        callAs('GET', '/api/backups', [], actorWithRole(UserRole::Admin))->assertOk();
    });

    it('may create users', function () {
        callAs('POST', '/api/users', [
            'name' => 'New', 'email' => 'new@test.com', 'password' => 'password123', 'role' => 'read-only',
        ], actorWithRole(UserRole::Admin))->assertCreated();
    });
});

it('rejects a mutating request without the csrf header', function () {
    callAs('POST', '/api/tags', ['name' => 'nope'], actorWithRole(UserRole::Admin), csrf: false)
        ->assertStatus(419);
});
