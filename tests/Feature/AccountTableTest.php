<?php

declare(strict_types=1);

use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Models\Account;
use Base\Tenant\Tests\Fixtures\User as HostUser;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

/**
 * El estado de suscripción no está en `accounts`: sale de Cashier, así que hay
 * que fabricar la suscripción a mano.
 */
function suscribir(Account $cuenta, string $estado, ?string $finDePrueba = null): Subscription
{
    return Subscription::create([
        'account_id' => $cuenta->getKey(),
        'type' => 'default',
        'stripe_id' => 'sub_'.Str::random(14),
        'stripe_status' => $estado,
        'trial_ends_at' => $finDePrueba,
    ]);
}

test('la búsqueda encuentra cuentas por nombre', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $otra = $this->createAccount(['name' => 'Globex SA']);
    $admin = $this->createUser($cuenta, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$otra->getKey()]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->set('search', 'Globex')
        ->assertSee('Globex SA')
        ->assertDontSee('Acme SL');
});

test('ordenar cuentas por nombre funciona en los dos sentidos', function () {
    $primera = $this->createAccount(['name' => 'Acme SL']);
    $segunda = $this->createAccount(['name' => 'Globex SA']);
    $tercera = $this->createAccount(['name' => 'Initech SL']);

    $admin = $this->createUser($primera, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$segunda->getKey(), $tercera->getKey()]);

    $this->actingAsTenant($admin, $primera);

    $componente = Livewire::test(AccountManager::class)->call('sort', 'name');

    $componente->assertSeeHtmlInOrder(['Acme SL', 'Globex SA', 'Initech SL']);

    $componente->call('sort', 'name')
        ->assertSeeHtmlInOrder(['Initech SL', 'Globex SA', 'Acme SL']);
});

/**
 * El `withCount('users')` ya estaba antes de tocar la consulta, así que ordenar
 * por el recuento no cuesta ninguna subconsulta nueva.
 */
test('ordenar cuentas por número de usuarios funciona', function () {
    $pocas = $this->createAccount(['name' => 'Pocas SL']);
    $muchas = $this->createAccount(['name' => 'Muchas SL']);

    $admin = $this->createUser($pocas, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$muchas->getKey()]);

    $this->createUser($muchas, 'customer-user');
    $this->createUser($muchas, 'customer-user');

    $this->actingAsTenant($admin, $pocas);

    Livewire::test(AccountManager::class)
        ->call('sort', 'users_count')
        ->assertSet('sortBy', 'users_count')
        ->assertSeeHtmlInOrder(['Pocas SL', 'Muchas SL'])
        ->call('sort', 'users_count')
        ->assertSeeHtmlInOrder(['Muchas SL', 'Pocas SL']);
});

test('una columna no declarada no ordena las cuentas', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::withQueryParams(['sortBy' => 'id'])
        ->test(AccountManager::class)
        ->assertOk()
        ->assertSet('sortBy', 'id')
        ->assertSee('Acme SL');
});

test('el filtro de suscripción separa activas, en prueba y sin suscripción', function () {
    $activa = $this->createAccount(['name' => 'Activa SL']);
    $prueba = $this->createAccount(['name' => 'Prueba SL']);
    $ninguna = $this->createAccount(['name' => 'Ninguna SL']);

    suscribir($activa, 'active');
    suscribir($prueba, 'trialing', now()->addDays(5)->toDateTimeString());

    $admin = $this->createUser($activa, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$prueba->getKey(), $ninguna->getKey()]);

    $this->actingAsTenant($admin, $activa);

    $componente = Livewire::test(AccountManager::class);

    $componente->set('filterSubscription', 'active')
        ->assertSee('Activa SL')
        ->assertDontSee('Prueba SL')
        ->assertDontSee('Ninguna SL');

    $componente->set('filterSubscription', 'trialing')
        ->assertSee('Prueba SL')
        ->assertDontSee('Activa SL')
        ->assertDontSee('Ninguna SL');

    $componente->set('filterSubscription', 'none')
        ->assertSee('Ninguna SL')
        ->assertDontSee('Activa SL')
        ->assertDontSee('Prueba SL');
});

/**
 * El scope `active()` de Cashier incluye las suscripciones en prueba, así que
 * una cuenta en prueba pasa las dos comprobaciones. Si el badge mira primero
 * «activa», ninguna cuenta se muestra nunca como en prueba.
 */
test('una cuenta en prueba no se anuncia como activa', function () {
    $prueba = $this->createAccount(['name' => 'Prueba SL']);
    suscribir($prueba, 'trialing', now()->addDays(5)->toDateTimeString());

    $admin = $this->createUser($prueba, 'customer-admin');

    $this->actingAsTenant($admin, $prueba);

    // Por el atributo y no por la etiqueta: el desplegable de filtros pinta
    // «Activa», «En prueba» y «Sin suscripción» en la misma página siempre.
    Livewire::test(AccountManager::class)
        ->assertSeeHtml('data-subscription="trialing"')
        ->assertDontSeeHtml('data-subscription="active"');
});

test('el filtro de suscripción vuelve a la primera página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->call('gotoPage', 3)
        ->set('filterSubscription', 'none')
        ->assertSet('paginators.page', 1);
});

test('limpiar filtros también suelta la suscripción', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->set('search', 'acme')
        ->set('filterSubscription', 'active')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterSubscription', '')
        ->assertViewHas('hasActiveFilters', false);
});

test('el vacío por búsqueda de cuentas no usa la copia del vacío inicial', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->set('search', 'no-existe-ninguna-asi')
        ->assertSee(__('base-tenant::common.empty_search'))
        ->assertDontSee(__('base-tenant::accounts.empty_description'));
});

test('una página de cuentas fuera de rango no se confunde con una tabla vacía', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->call('gotoPage', 5)
        ->assertOk()
        ->assertDontSee(__('base-tenant::common.empty_title'))
        ->assertDontSee(__('base-tenant::accounts.empty_description'));
});

/**
 * Una cabecera de tabla con más columnas que celdas pinta una tabla torcida sin
 * que nada falle. Se cuentan las dos y se comparan; no importa cuántas sean,
 * importa que coincidan.
 */
test('la tabla de cuentas pinta tantas celdas como columnas declara', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(AccountManager::class)->html();

    preg_match('/<thead.*?<\/thead>/s', $html, $cabecera);
    // Anclado al cuerpo real: el primer `<tbody` de la tabla es el del
    // esqueleto de carga, y medirlo aquí dejaría sin comprobar la fila que se
    // ve el resto del tiempo.
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($cabecera)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    // Con el espacio: `<th` sin él también cuenta la etiqueta `<thead`.
    $columnas = substr_count($cabecera[0], '<th ');
    $celdas = substr_count($primeraFila[0], '<td ');

    expect($columnas)->toBe(6)->and($celdas)->toBe($columnas);
});

/**
 * El esqueleto se pinta con la tabla ya montada, así que una fila con menos
 * celdas que la real endereza la tabla al desaparecer y la tuerce al aparecer.
 * Se cuentan las dos filas y se comparan.
 */
test('el esqueleto de carga de cuentas pinta tantas celdas como una fila real', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(AccountManager::class)->html();

    preg_match('/<tbody wire:loading\.delay.*?<tr.*?<\/tr>/s', $html, $esqueleto);
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($esqueleto)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    expect(substr_count($esqueleto[0], '<td '))
        ->toBe(substr_count($primeraFila[0], '<td '));
});

/**
 * La columna de propietario sale de una relación, y una relación que no casa
 * deja la celda vacía sin que nada falle. `Account::owner()` apunta al modelo
 * configurado, así que la prueba lo cambia por el de la aplicación anfitriona:
 * con el del paquete el fallo sería invisible aquí y visible en producción.
 */
test('la columna de propietario resuelve contra el modelo configurado', function () {
    config()->set('base-tenant.models.user', HostUser::class);

    $cuenta = $this->createAccount(['name' => 'Acme SL']);

    $duena = HostUser::create([
        'name' => 'Ana Dueña',
        'email' => 'ana.duena@example.com',
        'password' => bcrypt('password'),
        'account_id' => $cuenta->getKey(),
    ]);
    $duena->accounts()->syncWithoutDetaching([$cuenta->getKey()]);
    $cuenta->update(['user_id' => $duena->getKey()]);

    $admin = $this->createUser($cuenta, 'customer-admin');
    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountManager::class)
        ->assertSee('Ana Dueña')
        ->assertDontSee(__('base-tenant::accounts.no_owner'));
});

test('el recuento de la cabecera cuenta las cuentas alcanzables, no la página', function () {
    $primera = $this->createAccount(['name' => 'Acme SL']);
    $segunda = $this->createAccount(['name' => 'Globex SA']);

    $admin = $this->createUser($primera, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$segunda->getKey()]);

    $this->actingAsTenant($admin, $primera);

    $componente = Livewire::withQueryParams(['perPage' => '10'])->test(AccountManager::class);

    expect($componente->viewData('summary')['total'])->toBe(2);
});

/**
 * Una cuenta inactiva conserva sus datos pero nadie puede entrar a trabajar en
 * ella: eso no se ve recorriendo el listado, así que se anuncia arriba.
 */
test('la tira de contexto avisa de las cuentas inactivas', function () {
    $cuenta = $this->createAccount(['name' => 'Acme SL']);
    $parada = $this->createAccount(['name' => 'Parada SL', 'active' => false]);

    $admin = $this->createUser($cuenta, 'customer-admin');
    $admin->accounts()->syncWithoutDetaching([$parada->getKey()]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(AccountManager::class);

    expect($componente->viewData('summary')['inactive'])->toBe(1);

    $componente->assertSee(trans_choice('base-tenant::accounts.inactive_summary', 1, ['count' => 1]));
});
