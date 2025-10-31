<?php

declare(strict_types=1);

use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;

test('account can be created with factory', function () {
    $account = Account::factory()->create();

    expect($account)->toBeInstanceOf(Account::class)
        ->and($account->name)->not->toBeEmpty();
});

test('account has billable trait', function () {
    $account = Account::factory()->create();

    expect(method_exists($account, 'subscriptions'))->toBeTrue()
        ->and(method_exists($account, 'newSubscription'))->toBeTrue();
});

test('account can have owner', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($account->owner)->toBeInstanceOf(User::class)
        ->and($account->owner->id)->toBe($user->id);
});

test('account can have multiple users', function () {
    $account = Account::factory()->create();
    $users = User::factory()->count(3)->create();

    $account->users()->attach($users);

    expect($account->users)->toHaveCount(3);
});

test('account stripe email comes from owner', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $account = Account::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($account->stripeEmail())->toBe('owner@example.com');
});

test('account stripe name comes from account name', function () {
    $account = Account::factory()->create(['name' => 'Test Company']);

    expect($account->stripeName())->toBe('Test Company');
});
