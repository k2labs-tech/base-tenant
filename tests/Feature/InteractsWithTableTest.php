<?php

declare(strict_types=1);

use Base\Tenant\Livewire\UserManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('ordenar por una columna declarada cambia el orden', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'desc');
});

/**
 * `sortBy` viaja por la URL, así que lo escribe el usuario. Sin lista blanca
 * acaba tal cual en un `orderBy()`.
 */
test('una columna no declarada se ignora', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('sort', 'password')
        ->assertSet('sortBy', null)
        ->assertOk();
});

test('cambiar la búsqueda vuelve a la primera página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    // Livewire 4 no expone `$page`: la página vive en `$paginators['page']`.
    Livewire::test(UserManager::class)
        ->call('gotoPage', 3)
        ->assertSet('paginators.page', 3)
        ->set('search', 'ana')
        ->assertSet('paginators.page', 1);
});

test('limpiar filtros deja la tabla en su estado inicial', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'ana')
        ->call('sort', 'email')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('sortBy', null);
});

/**
 * `sort()` no es la única entrada: `sortBy` se hidrata desde la URL sin pasar
 * por ahí. La lista blanca tiene que volver a comprobarse al construir la
 * consulta, o `?sortBy=` acaba en el `orderBy()` tal cual.
 *
 * Se ordena por `id`, una columna real que no está declarada, porque una
 * inventada no sirve de prueba: SQLite degrada un identificador entrecomillado
 * que no reconoce a literal de texto y ordena por una constante, así que el
 * test pasaría igual sin lista blanca. Con `id` el orden inyectado es
 * observable y distinto del de por defecto.
 */
test('una columna real no declarada no llega a la consulta desde la URL', function () {
    $cuenta = $this->createAccount();

    // Se crean al revés: por `id` saldría Zoe primero; por `name`, Ana.
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe']);
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana']);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::withQueryParams([
        'sortBy' => 'id',
        'sortDirection' => 'asc',
    ])->test(UserManager::class);

    // La propiedad sí se hidrata con lo que venga; lo que no puede es ordenar.
    $componente->assertSet('sortBy', 'id')->assertOk();

    expect($componente->viewData('users')->pluck('name')->all())->toBe(['Ana', 'Zoe']);
});

/**
 * Livewire pasa los parámetros de URL como texto. Si `perPage` se quedara en
 * string, `paginate()` no fallaría: simplemente pagina raro.
 */
test('perPage sigue siendo un entero después de pasar por la URL', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::withQueryParams(['perPage' => '50'])->test(UserManager::class);

    $componente->assertSet('perPage', 50);

    expect($componente->get('perPage'))->toBeInt();
});

test('un perPage fuera de la lista vuelve al valor por defecto', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    // 97 caería dentro de cualquier rango razonable, pero no es una opción.
    foreach (['100000', '0', '-1', '97'] as $absurdo) {
        $componente = Livewire::withQueryParams(['perPage' => $absurdo])->test(UserManager::class);

        expect($componente->viewData('users')->perPage())->toBe(25);
    }

    $valido = Livewire::withQueryParams(['perPage' => '50'])->test(UserManager::class);

    expect($valido->viewData('users')->perPage())->toBe(50);
});
