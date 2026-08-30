<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Policies\RolePolicy;

beforeEach(function () {
    $this->syncPermissions();
});

test('a role grants its permissions inside the account', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    Tenant::runFor($account, function () use ($user): void {
        expect($user->hasPermission('users.create'))->toBeTrue()
            ->and($user->hasPermission('accounts.delete'))->toBeFalse();
    });
});

test('permissions do not cross into another account', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    $user->accounts()->syncWithoutDetaching([$other->getKey()]);

    $this->assertPermissionIsAccountScoped($account, $other, $user, 'users.create');
});

test('an unknown permission is denied instead of blowing up', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    Tenant::runFor($account, function () use ($user): void {
        expect($user->hasPermission('nothing.at.all'))->toBeFalse();
    });
});

test('a super admin passes every gate check', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $account);

    expect($admin->can('users.delete'))->toBeTrue()
        ->and($admin->can('viewAny', User::class))->toBeTrue();
});

test('an account can define its own role without affecting others', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $this->createRole($account, 'auditor', ['activity.view']);

    $auditor = $this->createUser($account, 'auditor');
    $outsider = $this->createUser($other, 'customer-viewer');

    Tenant::runFor($account, function () use ($auditor): void {
        expect($auditor->hasPermission('activity.view'))->toBeTrue();
    });

    Tenant::runFor($other, function () use ($outsider): void {
        expect($outsider->hasPermission('activity.view'))->toBeFalse();
    });
});

test('syncing roles in one account leaves the other account untouched', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    $user = $this->createUser($first, 'customer-admin');
    $user->accounts()->syncWithoutDetaching([$second->getKey()]);

    Tenant::runFor($second, fn () => $user->assignRole('customer-viewer'));

    Tenant::runFor($first, fn () => $user->syncRoles(['customer-user']));

    expect($user->rolesForAccount($first)->pluck('name')->all())->toBe(['customer-user'])
        ->and($user->rolesForAccount($second)->pluck('name')->all())->toBe(['customer-viewer']);
});

test('the user policy keeps administrators out of other accounts', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');
    $insider = $this->createUser($account, 'customer-user');
    $outsider = $this->createUser($other, 'customer-user');

    $this->actingAsTenant($admin, $account);

    expect($admin->can('update', $insider))->toBeTrue()
        ->and($admin->can('update', $outsider))->toBeFalse()
        ->and($admin->can('delete', $admin))->toBeFalse();
});

test('a viewer cannot manage users', function () {
    $account = $this->createAccount();
    $viewer = $this->createUser($account, 'customer-viewer');
    $target = $this->createUser($account, 'customer-user');

    $this->actingAsTenant($viewer, $account);

    expect($viewer->can('viewAny', User::class))->toBeFalse()
        ->and($viewer->can('update', $target))->toBeFalse()
        ->and($viewer->can('create', User::class))->toBeFalse();
});

test('global roles are read only for tenant administrators', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');
    $ownRole = $this->createRole($account, 'auditor');

    $this->actingAsTenant($admin, $account);

    $globalRole = Role::where('name', 'customer-user')->firstOrFail();

    expect($admin->can('update', $globalRole))->toBeFalse()
        ->and($admin->can('update', $ownRole))->toBeTrue()
        ->and($admin->can('delete', $ownRole))->toBeTrue();
});

test('system roles cannot be deleted', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $account);

    $systemRole = Role::where('name', 'administrator')->firstOrFail();

    expect((new RolePolicy)->delete($admin, $systemRole))->toBeFalse();
});

test('an invitation from another account cannot be revoked', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');

    $foreignInvite = Tenant::runFor($other, fn (): UserInvite => UserInvite::create([
        'email' => 'someone@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    $this->actingAsTenant($admin, $account);

    expect($admin->can('delete', $foreignInvite))->toBeFalse();
});
