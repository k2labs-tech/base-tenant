<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\ActivityLog;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\ActivityLog as ActivityLogModel;
use Base\Tenant\Tests\Fixtures\User as HostUser;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

function registrar(Account $cuenta, array $atributos = []): ActivityLogModel
{
    return Tenant::runFor($cuenta, fn (): ActivityLogModel => ActivityLogModel::create([
        'action' => 'created',
        'description' => 'algo pasó',
        ...$atributos,
    ]));
}

test('el registro se lee de lo más nuevo a lo más viejo sin pedirlo', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['description' => 'la vieja', 'created_at' => now()->subWeek()]);
    registrar($cuenta, ['description' => 'la reciente', 'created_at' => now()->subMinute()]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->assertSeeHtmlInOrder(['la reciente', 'la vieja']);
});

test('ordenar por fecha se puede invertir', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['description' => 'la vieja', 'created_at' => now()->subWeek()]);
    registrar($cuenta, ['description' => 'la reciente', 'created_at' => now()->subMinute()]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->call('sort', 'created_at')
        ->assertSeeHtmlInOrder(['la vieja', 'la reciente']);
});

test('una columna no declarada no ordena el registro', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['description' => 'la vieja', 'created_at' => now()->subWeek()]);
    registrar($cuenta, ['description' => 'la reciente', 'created_at' => now()->subMinute()]);

    $this->actingAsTenant($admin, $cuenta);

    // `description` es una columna real de la tabla, pero no está declarada.
    Livewire::withQueryParams(['sortBy' => 'description', 'sortDirection' => 'asc'])
        ->test(ActivityLog::class)
        ->assertOk()
        ->assertSeeHtmlInOrder(['la reciente', 'la vieja']);
});

test('el filtro por acción reduce el registro', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['action' => 'created', 'description' => 'una creación']);
    registrar($cuenta, ['action' => 'deleted', 'description' => 'un borrado']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->set('filterAction', 'deleted')
        ->assertSee('un borrado')
        ->assertDontSee('una creación');
});

test('el filtro por usuario reduce el registro', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana']);
    $otro = $this->createUser($cuenta, 'customer-user', ['name' => 'Bruno']);

    registrar($cuenta, [
        'description' => 'cosa de ana',
        'causer_id' => $admin->getKey(),
        'causer_type' => $admin->getMorphClass(),
    ]);
    registrar($cuenta, [
        'description' => 'cosa de bruno',
        'causer_id' => $otro->getKey(),
        'causer_type' => $otro->getMorphClass(),
    ]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->set('filterUser', (string) $admin->getKey())
        ->assertSee('cosa de ana')
        ->assertDontSee('cosa de bruno');
});

/**
 * El rango acota por fecha, y el extremo de arriba tiene que incluir el día
 * entero: con `<= '2026-01-10'` una entrada de esa tarde se queda fuera y
 * parece que no pasó nada ese día.
 */
test('el rango de fechas deja fuera lo que no cae dentro', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['description' => 'dentro del rango', 'created_at' => now()->subDays(3)]);
    registrar($cuenta, ['description' => 'fuera del rango', 'created_at' => now()->subDays(30)]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->set('filterFrom', now()->subDays(7)->toDateString())
        ->set('filterUntil', now()->toDateString())
        ->assertSee('dentro del rango')
        ->assertDontSee('fuera del rango');
});

test('el extremo superior del rango incluye el día entero', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $hoy = now()->startOfDay()->addHours(18);
    registrar($cuenta, ['description' => 'esta tarde', 'created_at' => $hoy]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->set('filterFrom', now()->toDateString())
        ->set('filterUntil', now()->toDateString())
        ->assertSee('esta tarde');
});

test('cambiar el rango vuelve a la primera página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->call('gotoPage', 3)
        ->set('filterFrom', now()->subDays(7)->toDateString())
        ->assertSet('paginators.page', 1);
});

test('limpiar filtros suelta acción, usuario y rango', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->set('search', 'algo')
        ->set('filterAction', 'created')
        ->set('filterUser', 'x')
        ->set('filterFrom', '2026-01-01')
        ->set('filterUntil', '2026-02-01')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterAction', '')
        ->assertSet('filterUser', '')
        ->assertSet('filterFrom', '')
        ->assertSet('filterUntil', '')
        ->assertViewHas('hasActiveFilters', false);
});

test('una página del registro fuera de rango no se confunde con un registro vacío', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta, ['description' => 'algo pasó']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->call('gotoPage', 9)
        ->assertOk()
        ->assertDontSee(__('base-tenant::activity.no_activities_found'));
});

/**
 * `livewire/flux-pro` no está instalado, así que la rama Pro no se ejecuta
 * nunca aquí. Lo que sí tiene que aguantar es la compilación: Blade resuelve
 * las etiquetas de componente al compilar, no al pintar, y un `@if` no protege
 * de eso. Por eso la rama Pro va por `x-dynamic-component`.
 */
test('la vista del registro compila sin flux pro', function () {
    expect(Flux\Flux::pro())->toBeFalse();

    $vista = dirname(__DIR__, 2).'/resources/views/livewire/activity-log.blade.php';

    $compilada = Blade::compileString(file_get_contents($vista));

    expect($compilada)->toBeString()->not->toBe('');
});

/**
 * Una cabecera con más columnas que celdas pinta una tabla torcida sin que nada
 * falle. Se cuentan las dos y se comparan.
 */
test('la tabla del registro pinta tantas celdas como columnas declara', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta);

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(ActivityLog::class)->html();

    preg_match('/<thead.*?<\/thead>/s', $html, $cabecera);
    // Anclado al cuerpo real: el primer `<tbody` de la tabla es el del
    // esqueleto de carga, y medirlo aquí dejaría sin comprobar la fila que se
    // ve el resto del tiempo.
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($cabecera)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    // Con el espacio: `<th` sin él también cuenta la etiqueta `<thead`.
    expect(substr_count($cabecera[0], '<th '))->toBe(5)
        ->and(substr_count($primeraFila[0], '<td '))->toBe(5);
});

/**
 * El esqueleto se pinta con la tabla ya montada, así que una fila con menos
 * celdas que la real endereza la tabla al desaparecer y la tuerce al aparecer.
 * Se cuentan las dos filas y se comparan.
 */
test('el esqueleto de carga del registro pinta tantas celdas como una fila real', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    registrar($cuenta);

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(ActivityLog::class)->html();

    preg_match('/<tbody wire:loading\.delay.*?<tr.*?<\/tr>/s', $html, $esqueleto);
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($esqueleto)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    expect(substr_count($esqueleto[0], '<td '))
        ->toBe(substr_count($primeraFila[0], '<td '));
});

/**
 * El autor sale de una relación polimórfica: `causer_type` guarda el nombre de
 * la clase configurada, que en una instalación real no es la del paquete. Si no
 * casa, la columna cae a «Sistema» y nadie se entera de que hubo un fallo.
 */
test('la columna de autor resuelve el modelo configurado que guardó el morph', function () {
    config()->set('base-tenant.models.user', HostUser::class);

    $cuenta = $this->createAccount();

    $autora = HostUser::create([
        'name' => 'Ana Autora',
        'email' => 'ana.autora@example.com',
        'password' => bcrypt('password'),
        'account_id' => $cuenta->getKey(),
    ]);
    $autora->accounts()->syncWithoutDetaching([$cuenta->getKey()]);

    registrar($cuenta, [
        'description' => 'algo hizo',
        'causer_id' => $autora->getKey(),
        'causer_type' => $autora->getMorphClass(),
    ]);

    $admin = $this->createUser($cuenta, 'customer-admin');
    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(ActivityLog::class)
        ->assertSee('Ana Autora')
        ->assertSee('ana.autora@example.com')
        ->assertDontSee(__('base-tenant::activity.system'));
});

test('el recuento de la cabecera cuenta el registro entero, no la página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    foreach (range(1, 3) as $indice) {
        registrar($cuenta, ['description' => "entrada {$indice}"]);
    }

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::withQueryParams(['perPage' => '10'])->test(ActivityLog::class);

    expect($componente->viewData('summary')['total'])->toBe(3);

    // Con un filtro puesto el recuento sigue siendo el del registro entero: es
    // el tamaño de lo que hay, no el de lo que se está mirando.
    $componente->set('filterAction', 'deleted');

    expect($componente->viewData('summary')['total'])->toBe(3);
});
