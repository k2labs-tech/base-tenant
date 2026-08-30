<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Livewire\InvitationManager;
use Base\Tenant\Livewire\NavigationManager;
use Base\Tenant\Livewire\RoleManager;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\UserInvite;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
    Menu::sync();
});

test('an administrator reaches the user manager', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    Livewire::test(UserManager::class)->assertOk();
});

test('a viewer is refused the user manager', function () {
    $account = $this->createAccount();
    $viewer = $this->createUser($account, 'customer-viewer');

    $this->actingAsTenant($viewer, $account);

    Livewire::test(UserManager::class)->assertForbidden();
});

test('the user manager only lists users of the account in context', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin', ['name' => 'Inside Admin']);
    $insider = $this->createUser($account, 'customer-user', ['name' => 'Inside User']);
    $outsider = $this->createUser($other, 'customer-user', ['name' => 'Outside User']);

    $this->actingAsTenant($admin, $account);

    Livewire::test(UserManager::class)
        ->assertSee('Inside User')
        ->assertDontSee('Outside User');
});

test('deleting a user from another account is refused', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');
    $outsider = $this->createUser($other, 'customer-user');

    $this->actingAsTenant($admin, $account);

    Livewire::test(UserManager::class)->call('confirmDelete', $outsider)->assertForbidden();
});

test('an administrator cannot delete their own account record', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    Livewire::test(UserManager::class)->call('confirmDelete', $admin)->assertForbidden();
});

test('the account manager only lists accounts the user belongs to', function () {
    $account = $this->createAccount(['name' => 'Mine Ltd']);
    $other = $this->createAccount(['name' => 'Theirs Ltd']);

    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    Livewire::test(AccountManager::class)
        ->assertSee('Mine Ltd')
        ->assertDontSee('Theirs Ltd');
});

test('the invitation manager lists only its own account invitations', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');

    Tenant::runFor($account, fn () => UserInvite::create([
        'email' => 'mine@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    Tenant::runFor($other, fn () => UserInvite::create([
        'email' => 'theirs@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    $this->actingAsTenant($admin, $account);

    Livewire::test(InvitationManager::class)
        ->assertSee('mine@example.com')
        ->assertDontSee('theirs@example.com');
});

test('revoking an invitation of another account is refused', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');

    $foreign = Tenant::runFor($other, fn () => UserInvite::create([
        'email' => 'theirs@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    $this->actingAsTenant($admin, $account);

    // The tenant scope hides it before the policy is even consulted.
    Livewire::test(InvitationManager::class)->call('revokeInvite', $foreign->getKey());
})->throws(ModelNotFoundException::class);

test('the role manager creates roles owned by the account', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    Livewire::test(RoleManager::class)
        ->set('newRoleName', 'Auditor')
        ->call('createRole')
        ->assertHasNoErrors();

    $role = Role::where('name', 'auditor')->firstOrFail();

    expect($role->account_id)->toBe($account->getKey())
        ->and($role->is_system)->toBeFalse();
});

/**
 * The global catalogue is the product's, not the tenant's. Listing it in the
 * editor only offered actions that ended in a denial, so it is out of scope
 * entirely: not on screen, and not reachable by id either.
 */
test('the role manager hides global roles from a tenant', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');
    $own = $this->createRole($account, 'auditor');

    $this->actingAsTenant($admin, $account);

    Livewire::test(RoleManager::class)
        ->assertSee('auditor')
        ->assertDontSee('Customer User');
});

test('the role manager cannot reach a global role by id', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    $global = Role::where('name', 'customer-user')->firstOrFail();

    Livewire::test(RoleManager::class)->call('edit', $global->getKey());
})->throws(ModelNotFoundException::class);

test('platform staff still see and edit the global catalogue', function () {
    $account = $this->createAccount();
    $staff = $this->createUser($account, null, ['is_admin' => true]);

    $this->actingAsTenant($staff, $account);

    $global = Role::where('name', 'customer-user')->firstOrFail();

    Livewire::test(RoleManager::class)
        ->call('edit', $global->getKey())
        ->assertSet('editingRoleId', $global->getKey())
        ->assertOk();
});

test('permissions saved on an account role take effect immediately', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');
    $role = $this->createRole($account, 'auditor');
    $auditor = $this->createUser($account, 'auditor');

    $this->actingAsTenant($admin, $account);

    Livewire::test(RoleManager::class)
        ->call('edit', $role->getKey())
        ->set('selectedPermissions', ['activity.view'])
        ->call('savePermissions');

    Tenant::runFor($account, function () use ($auditor): void {
        expect($auditor->fresh()->hasPermission('activity.view'))->toBeTrue();
    });
});

test('the navigation manager hides an entry for one account only', function () {
    $account = $this->createAccount();
    $other = $this->createAccount();

    $admin = $this->createUser($account, 'customer-admin');
    $otherAdmin = $this->createUser($other, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    Livewire::test(NavigationManager::class)->call('toggle', 'accounts');

    expect(Menu::tree('main')->pluck('key')->all())->not->toContain('accounts');

    $this->actingAsTenant($otherAdmin, $other);

    expect(Menu::tree('main')->pluck('key')->all())->toContain('accounts');
});

test('a user without the menus permission cannot reach the navigation manager', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account, 'customer-user');

    $this->actingAsTenant($user, $account);

    Livewire::test(NavigationManager::class)->assertForbidden();
});
