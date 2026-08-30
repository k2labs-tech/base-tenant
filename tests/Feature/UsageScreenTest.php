<?php

declare(strict_types=1);

use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Livewire\UsageManager;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\UsageEvent;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();

    config([
        'base-tenant.metering.metrics' => [
            'storage.bytes' => [
                'type' => 'gauge',
                'feature' => 'max_storage_gb',
                'scale' => 1073741824,
            ],
            'api.calls' => [
                'type' => 'counter',
                'reset' => 'day',
            ],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

test('la pantalla enseña todas las métricas declaradas', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UsageManager::class)
        ->assertOk()
        ->assertSee('storage.bytes')
        ->assertSee('api.calls');
});

test('quien no tiene el permiso no entra', function () {
    $cuenta = $this->createAccount();
    $mirona = $this->createUser($cuenta, 'customer-viewer');

    $this->actingAsTenant($mirona, $cuenta);

    Livewire::test(UsageManager::class)->assertForbidden();
});

/**
 * El estado por consumo se lee de un vistazo por el color de la barra. El
 * asidero estable es el atributo, porque el color vive en una clase de Tailwind
 * que puede cambiar sin que cambie el significado.
 */
test('el nivel de cada fila sale del consumo, no de la métrica', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    // 1 GB de plan; medio consumido.
    Meter::for($cuenta)->set('storage.bytes', 536870912);

    Livewire::test(UsageManager::class)->assertSeeHtml('data-usage-level="ok"');

    Meter::for($cuenta)->set('storage.bytes', 966367641);
    Livewire::test(UsageManager::class)->assertSeeHtml('data-usage-level="warning"');

    Meter::for($cuenta)->set('storage.bytes', 1073741824);
    Livewire::test(UsageManager::class)->assertSeeHtml('data-usage-level="exceeded"');
});

test('una métrica sin tope no finge tener uno', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UsageManager::class)
        ->assertSeeHtml('data-usage-level="none"')
        ->assertSee(__('base-tenant::metering.no_limit'));
});

test('la tira de contexto cuenta lo que está en el límite y lo que se acerca', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(UsageManager::class);

    expect($componente->viewData('summary'))->toMatchArray(['at_limit' => 0, 'approaching' => 0]);

    Meter::for($cuenta)->set('storage.bytes', 1073741824);

    expect(Livewire::test(UsageManager::class)->viewData('summary'))
        ->toMatchArray(['at_limit' => 1, 'approaching' => 0]);
});

test('el filtro separa lo que el plan limita de lo que solo se mide', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(UsageManager::class);

    $componente->set('filterScope', 'capped')
        ->assertSee('storage.bytes')
        ->assertDontSee('api.calls');

    $componente->set('filterScope', 'uncapped')
        ->assertSee('api.calls')
        ->assertDontSee('storage.bytes');
});

/**
 * Una columna entera de infinitos ocupa ancho y no informa: la de disponible
 * solo aparece si algo está topado de verdad.
 */
test('la columna de disponible desaparece cuando nada está topado', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UsageManager::class)
        ->assertViewHas('showsRemaining', true);

    Feature::for($cuenta)->set('max_storage_gb', -1);

    Livewire::test(UsageManager::class)
        ->assertViewHas('showsRemaining', false);
});

/**
 * El esqueleto se pinta con la tabla ya montada: una fila con menos celdas que
 * la real endereza la tabla al desaparecer y la tuerce al aparecer.
 */
test('el esqueleto pinta tantas celdas como una fila real', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(UsageManager::class)->html();

    preg_match('/<tbody wire:loading\.delay.*?<tr.*?<\/tr>/s', $html, $esqueleto);
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($esqueleto)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    expect(substr_count($esqueleto[0], '<td '))
        ->toBe(substr_count($primeraFila[0], '<td '));
});

test('la pantalla de consumo no ofrece tamaño de página porque no pagina', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UsageManager::class)
        ->assertOk()
        ->assertDontSeeHtml('wire:model.live="perPage"');
});

/**
 * Middleware. La unidad se cobra antes de hacer el trabajo, y si el trabajo no
 * sale, se devuelve.
 */
test('el middleware cobra la unidad y deja pasar mientras haya sitio', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    Route::middleware(['web', 'base-tenant.metered:api.calls,3'])
        ->get('/prueba-medida', fn () => 'ok');

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-medida')->assertOk();

    expect(Meter::for($cuenta)->current('api.calls'))->toBe(3);
});

test('el middleware devuelve un 402 cuando no queda sitio', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    // `max_projects` vale 1 en el plan gratuito.
    // El array entero y no notación de puntos: la clave de la métrica lleva un
    // punto, y `config()` lo leería como un segundo nivel.
    config(['base-tenant.metering.metrics' => [
        'projects.created' => ['type' => 'counter', 'feature' => 'max_projects'],
    ]]);
    app(MetricRegistry::class)->flush();

    Route::middleware(['web', 'base-tenant.metered:projects.created'])
        ->get('/prueba-tope', fn () => 'ok');

    $this->actingAsTenant($usuaria, $cuenta);

    // La pantalla del 402 se pinta dentro del layout de la aplicación, que
    // pide el manifiesto de Vite: aquí no hay assets construidos.
    $this->withoutVite();

    $this->get('/prueba-tope')->assertOk();

    $this->get('/prueba-tope')
        ->assertStatus(402)
        ->assertSee(__('base-tenant::metering.limit_reached_title'));
});

/**
 * Un 402 en JSON tiene que ser legible por una máquina: un cliente de API
 * necesita saber qué métrica y con qué tope, no una página HTML.
 */
test('el 402 llega como JSON cuando lo piden así', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    // El array entero y no notación de puntos: la clave de la métrica lleva un
    // punto, y `config()` lo leería como un segundo nivel.
    config(['base-tenant.metering.metrics' => [
        'projects.created' => ['type' => 'counter', 'feature' => 'max_projects'],
    ]]);
    app(MetricRegistry::class)->flush();

    Route::middleware(['web', 'base-tenant.metered:projects.created'])
        ->get('/prueba-json', fn () => 'ok');

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-json');

    $this->getJson('/prueba-json')
        ->assertStatus(402)
        ->assertJsonPath('metric', 'projects.created')
        ->assertJsonPath('limit', 1);
});

/**
 * La unidad se cobra antes de hacer el trabajo. Si el trabajo falla, cobrarla
 * igual sería facturar por lo que no ocurrió.
 */
test('la unidad se devuelve cuando la petición falla', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    Route::middleware(['web', 'base-tenant.metered:api.calls,5'])
        ->get('/prueba-rota', fn () => response('vaya', 500));

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-rota')->assertStatus(500);

    expect(Meter::for($cuenta)->current('api.calls'))->toBe(0)
        // La devolución es un movimiento más, no un borrado: el trail sigue
        // contando lo que se intentó.
        ->and(UsageEvent::where('metric', 'api.calls')->count())->toBe(2);
});

test('con el módulo apagado el middleware no cobra ni estorba', function () {
    config(['base-tenant.metering.enabled' => false]);

    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    Route::middleware(['web', 'base-tenant.metered:api.calls'])
        ->get('/prueba-apagada', fn () => 'ok');

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-apagada')->assertOk();
});
