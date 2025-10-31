<?php

declare(strict_types=1);

use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;

test('user can be created with factory', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->not->toBeEmpty()
        ->and($user->name)->not->toBeEmpty();
});

test('user can have roles', function () {
    $user = User::factory()->create();
    $role = Role::create([
        'key' => 'test-role',
        'name' => 'Test Role',
        'is_system' => false,
    ]);

    $user->roles()->attach($role);

    expect($user->roles)->toHaveCount(1)
        ->and($user->roles->first()->key)->toBe('test-role');
});

test('user can check if has role', function () {
    $user = User::factory()->create();
    $role = Role::create([
        'key' => 'customer-admin',
        'name' => 'Customer Admin',
        'is_system' => false,
    ]);

    $user->roles()->attach($role);
    $user->storeRolesSession();

    expect($user->hasRole('customer-admin'))->toBeTrue()
        ->and($user->hasRole('non-existent-role'))->toBeFalse();
});

test('user can add role', function () {
    $user = User::factory()->create();
    Role::create([
        'key' => 'customer-user',
        'name' => 'Customer User',
        'is_system' => false,
    ]);

    $result = $user->addRole('customer-user');

    expect($result)->toBeTrue()
        ->and($user->roles()->count())->toBe(1);
});

test('user initials are generated correctly', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    expect($user->initials)->toBe('JD');
});

test('user can enable two factor authentication', function () {
    $user = User::factory()->create();
    $secret = 'test-secret';

    $user->enableTwoFactorAuthentication($secret);

    expect($user->two_factor_secret)->toBe($secret)
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});
