<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;

test('user can be created with factory', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->not->toBeEmpty()
        ->and($user->name)->not->toBeEmpty();
});

test('user holds roles inside the account in context', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    Tenant::runFor($account, function () use ($user): void {
        expect($user->hasRole('customer-admin'))->toBeTrue()
            ->and($user->hasRole('customer-viewer'))->toBeFalse();
    });
});

test('addRole assigns a configured role in the current account', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $user = $this->createUser($account);

    Tenant::runFor($account, function () use ($user): void {
        expect($user->addRole('customer-user'))->toBeTrue()
            ->and($user->addRole('does-not-exist'))->toBeFalse()
            ->and($user->roles()->count())->toBe(1);
    });
});

test('role checks no longer depend on the session', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    session()->flush();

    $fresh = User::findOrFail($user->getKey());

    Tenant::runFor($account, function () use ($fresh): void {
        expect($fresh->hasRole('customer-admin'))->toBeTrue();
    });
});

test('checking another user does not return the roles of the authenticated one', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');
    $viewer = $this->createUser($account, 'customer-viewer');

    $this->actingAsTenant($admin, $account);

    expect($viewer->hasRole('customer-admin'))->toBeFalse()
        ->and($viewer->hasRole('customer-viewer'))->toBeTrue();
});

test('user initials are generated correctly', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    expect($user->initials)->toBe('JD');
});

test('user can enable two factor authentication', function () {
    $user = User::factory()->create();

    $user->enableTwoFactorAuthentication('test-secret');

    expect($user->two_factor_secret)->toBe('test-secret')
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

test('currency formatting uses the separators stored on the user', function () {
    $user = User::factory()->create([
        'decimals_separator' => ',',
        'thousands_separator' => '.',
    ]);

    expect($user->applyCurrencyFormat(1234.5))->toBe('1.234,50');
});

test('belongsToAccount answers for both the primary account and the pivot', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();
    $user = $this->createUser($account);

    expect($user->belongsToAccount($account))->toBeTrue()
        ->and($user->belongsToAccount($other))->toBeFalse()
        ->and($user->belongsToAccount(null))->toBeFalse();
});
