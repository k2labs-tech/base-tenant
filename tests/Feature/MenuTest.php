<?php

declare(strict_types=1);

use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Menu;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Menu\MenuBuilder;
use Base\Tenant\Models\Menu as MenuModel;
use Base\Tenant\Models\MenuItem;
use Base\Tenant\Models\UserInvite;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->syncPermissions();
    Menu::sync();
});

test('syncing writes the declared menus to the database', function () {
    expect(MenuModel::whereNull('account_id')->pluck('key')->all())
        ->toContain('main', 'settings');

    $main = MenuModel::where('key', 'main')->firstOrFail();

    expect($main->items()->pluck('key')->all())
        ->toContain('dashboard', 'users', 'invitations', 'accounts');
});

test('syncing twice does not duplicate entries', function () {
    $before = MenuItem::count();

    Menu::sync();

    expect(MenuItem::count())->toBe($before);
});

test('entries the user has no permission for are hidden', function () {
    $account = $this->createAccount();
    $viewer = $this->createUser($account, 'customer-viewer');

    $this->actingAsTenant($viewer, $account);

    $keys = Menu::tree('main')->pluck('key')->all();

    expect($keys)->toContain('dashboard', 'accounts')
        ->and($keys)->not->toContain('users', 'invitations');
});

test('an administrator sees the entries their permissions unlock', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    expect(Menu::tree('main')->pluck('key')->all())
        ->toContain('dashboard', 'users', 'invitations', 'accounts');
});

test('an account override changes the label and order for that account only', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    $admin = $this->createUser($first, 'customer-admin');
    $otherAdmin = $this->createUser($second, 'customer-admin');

    $menu = MenuModel::where('key', 'main')->firstOrFail();

    MenuItem::create([
        'menu_id' => $menu->id,
        'account_id' => $first->getKey(),
        'key' => 'users',
        'label' => 'Colleagues',
        'position' => 5,
        'is_active' => true,
        'is_system' => true,
    ]);

    $this->actingAsTenant($admin, $first);
    $ownTree = Menu::tree('main');

    expect($ownTree->firstWhere('key', 'users')['title'])->toBe('Colleagues')
        ->and($ownTree->first()['key'])->toBe('users');

    $this->actingAsTenant($otherAdmin, $second);
    $otherTree = Menu::tree('main');

    expect($otherTree->firstWhere('key', 'users')['title'])->not->toBe('Colleagues')
        ->and($otherTree->first()['key'])->toBe('dashboard');
});

test('an account can hide an entry without affecting the product menu', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $menu = MenuModel::where('key', 'main')->firstOrFail();

    MenuItem::create([
        'menu_id' => $menu->id,
        'account_id' => $account->getKey(),
        'key' => 'accounts',
        'position' => 40,
        'is_active' => false,
        'is_system' => true,
    ]);

    $this->actingAsTenant($admin, $account);

    expect(Menu::tree('main')->pluck('key')->all())->not->toContain('accounts');

    expect(MenuItem::whereNull('account_id')->where('key', 'accounts')->first()->is_active)->toBeTrue();
});

test('entries gated by a feature flag stay hidden until it is enabled', function () {
    Menu::register('main', function (MenuBuilder $menu): void {
        $menu->item('exports')
            ->label('Exports')
            ->route('base-tenant.dashboard')
            ->feature('export')
            ->position(90);
    });

    Menu::sync();

    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    expect(Menu::tree('main')->pluck('key')->all())->not->toContain('exports');

    Feature::for($account)->set('export', true);

    expect(Menu::tree('main')->pluck('key')->all())->toContain('exports');
});

test('badges are resolved on every render', function () {
    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    expect(Menu::tree('main')->firstWhere('key', 'invitations')['badge'])->toBeNull();

    Tenant::runFor($account, fn () => UserInvite::create([
        'email' => 'someone@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    expect(Menu::tree('main')->firstWhere('key', 'invitations')['badge'])->toBe('1');
});

test('nested entries are returned as a tree', function () {
    Menu::register('main', function (MenuBuilder $menu): void {
        $menu->item('reports')
            ->label('Reports')
            ->position(80)
            ->children(function (MenuBuilder $children): void {
                $children->item('reports.sales')->label('Sales')->route('base-tenant.dashboard');
            });
    });

    Menu::sync();

    $account = $this->createAccount();
    $admin = $this->createUser($account, 'customer-admin');

    $this->actingAsTenant($admin, $account);

    $reports = Menu::tree('main')->firstWhere('key', 'reports');

    expect($reports['children'])->toHaveCount(1)
        ->and($reports['children'][0]['key'])->toBe('reports.sales');
});

test('entries no longer declared are removed on the next sync', function () {
    $menu = MenuModel::where('key', 'main')->firstOrFail();

    MenuItem::create([
        'menu_id' => $menu->id,
        'account_id' => null,
        'key' => 'stale',
        'label' => 'Stale',
        'is_system' => true,
        'position' => 99,
    ]);

    Menu::sync();

    expect(MenuItem::whereNull('account_id')->where('key', 'stale')->exists())->toBeFalse();
});

/**
 * Las migas salen del propio menú y no de una segunda lista que mantener: el
 * árbol ya sabe qué entrada está activa y de qué sección cuelga.
 *
 * Se resuelve la ruta de verdad porque el árbol decide con `routeIs()`, que
 * necesita una ruta casada y no basta con la URI.
 */
test('el rastro nombra la sección y la entrada activa', function () {
    // Con permiso: la entrada de usuarios está detrás de `users.view`, así que
    // sin usuario el árbol la esconde y el rastro saldría vacío.
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $this->actingAsTenant($admin, $cuenta);

    $ruta = Route::getRoutes()->getByName('base-tenant.users.index');
    request()->setRouteResolver(fn () => $ruta);

    $rastro = Menu::trail($admin);

    expect($rastro)->not->toBeEmpty()
        ->and($rastro[0]['title'])->toBe(__('base-tenant::menus.names.main'))
        ->and(collect($rastro)->last()['title'])->toBe(__('base-tenant::app.navigation.users'));
});

test('sin entrada activa el rastro viene vacío', function () {
    $ruta = Route::getRoutes()->getByName('base-tenant.profile');
    request()->setRouteResolver(fn () => $ruta);

    // El perfil sí está en el menú de ajustes, así que se comprueba con una
    // ruta que no aparece en ningún menú.
    request()->setRouteResolver(fn () => Route::getRoutes()->getByName('base-tenant.login'));

    expect(Menu::trail())->toBe([]);
});
