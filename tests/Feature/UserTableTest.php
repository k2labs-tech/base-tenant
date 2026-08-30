<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\Tests\Fixtures\User as HostUser;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('la búsqueda encuentra por nombre', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Bruno Díaz']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'Bruno')
        ->assertSee('Bruno Díaz')
        ->assertDontSee('Ana Gómez');
});

test('la búsqueda encuentra por correo', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', [
        'name' => 'Ana Gómez',
        'email' => 'ana@ejemplo.test',
    ]);
    $this->createUser($cuenta, 'customer-user', [
        'name' => 'Bruno Díaz',
        'email' => 'bruno@otrositio.test',
    ]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'otrositio')
        ->assertSee('Bruno Díaz')
        ->assertDontSee('Ana Gómez');
});

test('ordenar por nombre funciona en los dos sentidos', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Bruno Díaz']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Carla Ruiz']);

    $this->actingAsTenant($admin, $cuenta);

    // `assertSeeInOrder` no está sobrescrito por Livewire: cae en el de Laravel,
    // que mira el cuerpo JSON crudo, donde el HTML va codificado y los acentos
    // aparecen como `ó`. El equivalente que sí mira el HTML del componente
    // es `assertSeeHtmlInOrder`.
    $componente = Livewire::test(UserManager::class)->call('sort', 'name');

    $componente->assertSeeHtmlInOrder(['Ana Gómez', 'Bruno Díaz', 'Carla Ruiz']);

    $componente->call('sort', 'name')
        ->assertSeeHtmlInOrder(['Carla Ruiz', 'Bruno Díaz', 'Ana Gómez']);
});

test('el filtro por rol reduce la lista', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Bruno Díaz']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->assertSee('Bruno Díaz')
        ->set('filterRole', 'customer-admin')
        ->assertSee('Ana Gómez')
        ->assertDontSee('Bruno Díaz');
});

test('el filtro por rol vuelve a la primera página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('gotoPage', 3)
        ->set('filterRole', 'customer-admin')
        ->assertSet('paginators.page', 1);
});

/**
 * Los dos vacíos dicen cosas distintas: uno invita a crear el primer usuario y
 * el otro a soltar los filtros. Si comparten copia, quien busca mal cree que no
 * hay nada dado de alta.
 */
test('el vacío por búsqueda no usa la copia del vacío inicial', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'no-existe-nadie-asi')
        ->assertSee(__('base-tenant::common.empty_search'))
        ->assertSee(__('base-tenant::common.clear_filters'))
        ->assertDontSee(__('base-tenant::users.empty_description'));
});

test('sin filtros activos la tabla no ofrece limpiarlos', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->assertViewHas('hasActiveFilters', false)
        ->assertDontSee(__('base-tenant::common.empty_search'))
        ->set('search', 'Ana')
        ->assertViewHas('hasActiveFilters', true);
});

test('limpiar filtros también suelta el rol', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'ana')
        ->set('filterRole', 'customer-admin')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterRole', '')
        ->assertViewHas('hasActiveFilters', false);
});

/**
 * `?page=5` con una sola fila deja la página vacía sin que la tabla lo esté.
 * Anunciar ahí «aún no hay nada» y ofrecer crear el primer usuario es mentir
 * sobre datos que existen, y se llega por URL, que es justo lo que estas
 * pantallas ahora comparten por enlace.
 */
test('una página fuera de rango no se confunde con una tabla vacía', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('gotoPage', 5)
        ->assertOk()
        ->assertDontSee(__('base-tenant::common.empty_title'))
        ->assertDontSee(__('base-tenant::users.empty_description'))
        ->assertDontSee(__('base-tenant::common.empty_search'));
});

/**
 * La columna de cuentas solo existe para el administrador de sistema, así que
 * es la única rama de la tabla que el resto de tests no llega a pintar.
 */
test('el administrador de sistema ve la columna de cuentas', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $staff = $this->createUser($cuenta, null, ['is_admin' => true, 'name' => 'Root']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Bruno Díaz']);

    $this->actingAsTenant($staff, $cuenta);

    Livewire::test(UserManager::class)
        ->assertOk()
        ->assertSee(__('base-tenant::users.accounts'))
        ->assertSee('Acme SL')
        ->assertSee('Bruno Díaz');
});

/**
 * Un rol inexistente llega por la URL igual que `sortBy`. Aquí no hay riesgo de
 * ordenación, pero la lista no puede quedarse mostrando todo como si no se
 * hubiera filtrado: eso haría creer que ese rol lo tiene todo el mundo.
 */
test('filtrar por un rol inexistente no devuelve a nadie', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gómez']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::withQueryParams(['filterRole' => 'rol-que-no-existe'])
        ->test(UserManager::class)
        ->assertOk()
        ->assertDontSee('Ana Gómez');
});

/**
 * Los roles se guardan con el `model_type` del modelo configurado, que en una
 * aplicación real es el de la aplicación y no el del paquete. Consultar la
 * clase del paquete produce un morph distinto, la relación no casa y la
 * columna de roles sale vacía sin que nada falle.
 *
 * En la suite el modelo configurado es el del paquete, así que este test
 * apunta la configuración a una subclase: sin eso, el fallo es invisible aquí
 * y visible en cada instalación real.
 */
test('la lista usa el modelo de usuario configurado, no el del paquete', function () {
    config()->set('base-tenant.models.user', HostUser::class);

    $cuenta = $this->createAccount();

    // Se crea con la clase configurada, no con la factoría del paquete: el rol
    // se guarda con el `model_type` de la instancia, y una factoría del paquete
    // devolvería su propia clase, que es justo el fallo que se está probando.
    $admin = HostUser::create([
        'name' => 'Ana Gil',
        'email' => 'ana@example.com',
        'password' => bcrypt('password'),
        'account_id' => $cuenta->getKey(),
    ]);
    $admin->accounts()->syncWithoutDetaching([$cuenta->getKey()]);
    Tenant::runFor($cuenta, fn () => $admin->assignRole('customer-admin'));

    $this->actingAsTenant($admin, $cuenta);

    $usuarios = Livewire::test(UserManager::class)->viewData('users');

    expect($usuarios->first())->toBeInstanceOf(HostUser::class)
        ->and($usuarios->first()->roles->pluck('name')->all())->toContain('customer-admin');
});
