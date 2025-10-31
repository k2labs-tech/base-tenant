<?php

declare(strict_types=1);

use Base\Tenant\Models\Role;
use Base\Tenant\Traits\HasExtensibleRoles;

test('role can be created', function () {
    $role = Role::create([
        'key' => 'test-role',
        'name' => 'Test Role',
        'is_system' => false,
    ]);

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->key)->toBe('test-role')
        ->and($role->is_system)->toBeFalse();
});

test('role has extensible roles trait', function () {
    expect(class_uses(Role::class))->toContain(HasExtensibleRoles::class);
});

test('can get all configured roles', function () {
    $roles = Role::getAllConfiguredRoles();

    expect($roles)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($roles->count())->toBeGreaterThan(0);
});

test('can get system roles', function () {
    $roles = Role::getSystemRoles();

    expect($roles)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($roles->every(fn ($role) => $role['is_system'] === true))->toBeTrue();
});

test('can get customer roles', function () {
    $roles = Role::getCustomerRoles();

    expect($roles)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($roles->every(fn ($role) => $role['is_system'] === false))->toBeTrue();
});

test('can check if role exists', function () {
    expect(Role::roleExists('administrator'))->toBeTrue()
        ->and(Role::roleExists('non-existent-role'))->toBeFalse();
});

test('can sync roles to database', function () {
    Role::syncRolesToDatabase();

    $rolesInDb = Role::all();
    $configuredRoles = Role::getAllConfiguredRoles();

    expect($rolesInDb->count())->toBe($configuredRoles->count());
});
