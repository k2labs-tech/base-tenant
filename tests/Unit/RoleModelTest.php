<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Support\Collection;

test('role keeps key in sync with the spatie name', function () {
    $role = Role::create([
        'name' => 'test-role',
        'display_name' => 'Test Role',
        'guard_name' => 'web',
        'account_id' => null,
        'is_system' => false,
    ]);

    expect($role->key)->toBe('test-role')
        ->and($role->label)->toBe('Test Role')
        ->and($role->is_system)->toBeFalse()
        ->and($role->isGlobal())->toBeTrue();
});

test('role falls back to its name when no label is given', function () {
    $role = Role::create(['name' => 'plain', 'guard_name' => 'web', 'account_id' => null]);

    expect($role->label)->toBe('plain');
});

test('role has extensible roles trait', function () {
    expect(class_uses(Role::class))->toContain(HasExtensibleRoles::class);
});

test('configured roles are readable from config', function () {
    expect(Role::getAllConfiguredRoles())->toBeInstanceOf(Collection::class)
        ->and(Role::getAllConfiguredRoles()->count())->toBeGreaterThan(0)
        ->and(Role::getSystemRoles()->every(fn (array $role): bool => $role['is_system'] === true))->toBeTrue()
        ->and(Role::getCustomerRoles()->every(fn (array $role): bool => $role['is_system'] === false))->toBeTrue()
        ->and(Role::roleExists('administrator'))->toBeTrue()
        ->and(Role::roleExists('non-existent-role'))->toBeFalse();
});

test('syncing writes every configured role and permission', function () {
    $this->syncPermissions();

    expect(Role::whereNull('account_id')->count())->toBe(Role::getAllConfiguredRoles()->count())
        ->and(Permission::count())->toBe(count(Permission::allConfiguredNames()));
});

test('sync grants the administrator role every permission', function () {
    $this->syncPermissions();

    $administrator = Role::where('name', 'administrator')->firstOrFail();

    expect($administrator->permissions)->toHaveCount(count(Permission::allConfiguredNames()));
});

test('sync expands wildcard permissions', function () {
    $this->syncPermissions();

    $names = Role::where('name', 'customer-admin')->firstOrFail()
        ->permissions->pluck('name')->all();

    expect($names)->toContain('users.view', 'users.create', 'users.update', 'users.delete')
        ->and($names)->not->toContain('accounts.delete');
});

test('assignable scope only returns global roles and the ones of the account in context', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $other = $this->createAccount();

    $own = $this->createRole($account, 'own-role');
    $foreign = $this->createRole($other, 'foreign-role');

    Tenant::runFor($account, function () use ($own, $foreign): void {
        $names = Role::query()->assignable()->pluck('name')->all();

        expect($names)->toContain($own->name, 'customer-admin')
            ->and($names)->not->toContain($foreign->name);
    });
});
