<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\InvitationService;

beforeEach(function () {
    $this->syncPermissions();
});

test('creating a primary account assigns the role inside that account', function () {
    $user = User::factory()->create(['account_id' => null]);

    $account = $user->createPrimaryAccountAndSetRole('Acme Ltd');

    expect($account)->toBeInstanceOf(Account::class)
        ->and($account->name)->toBe('Acme Ltd')
        ->and($user->fresh()->account_id)->toBe($account->getKey())
        ->and($user->rolesForAccount($account)->pluck('name')->all())->toBe(['customer-admin']);
});

test('a freshly registered owner can manage their own account', function () {
    $user = User::factory()->create(['account_id' => null]);
    $account = $user->createPrimaryAccountAndSetRole('Acme Ltd');

    $this->actingAsTenant($user->fresh(), $account);

    expect(auth()->user()->can('viewAny', User::class))->toBeTrue()
        ->and(auth()->user()->can('create', User::class))->toBeTrue()
        ->and(auth()->user()->can('update', $account))->toBeTrue();
});

test('accepting an invitation grants the role in the inviting account', function () {
    $account = $this->createAccount();
    $inviter = $this->createUser($account, 'customer-admin');
    $role = Role::where('name', 'customer-user')->firstOrFail();

    $invite = Tenant::runFor(
        $account,
        fn (): UserInvite => InvitationService::send('newcomer@example.com', $account->getKey(), $role->getKey(), $inviter)
    );

    $newcomer = User::factory()->create(['account_id' => null, 'email' => 'newcomer@example.com']);

    InvitationService::accept($invite, $newcomer);

    expect($newcomer->fresh()->account_id)->toBe($account->getKey())
        ->and($newcomer->rolesForAccount($account)->pluck('name')->all())->toBe(['customer-user']);
});

test('an invitation is reachable from a public route without a tenant', function () {
    $account = $this->createAccount();
    $inviter = $this->createUser($account, 'customer-admin');
    $role = Role::where('name', 'customer-user')->firstOrFail();

    $invite = Tenant::runFor(
        $account,
        fn (): UserInvite => InvitationService::send('newcomer@example.com', $account->getKey(), $role->getKey(), $inviter)
    );

    Tenant::forget();

    $this->get(route('base-tenant.invitations.accept', ['token' => $invite->token]))
        ->assertRedirect();
});
