<?php

declare(strict_types=1);

use Base\Tenant\Livewire\FeatureManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('la búsqueda encuentra funcionalidades por clave', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(FeatureManager::class)
        ->set('search', 'api_access')
        ->assertSee('api_access')
        ->assertDontSee('max_projects');
});

test('ordenar por clave funciona en los dos sentidos', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class)->call('sort', 'key');

    $componente->assertSeeHtmlInOrder(['api_access', 'export', 'max_projects']);

    $componente->call('sort', 'key')
        ->assertSeeHtmlInOrder(['priority_support', 'max_users', 'max_projects']);
});

/**
 * Este listado no sale de una consulta, así que la lista blanca la aplica
 * `applySortToCollection()`. La garantía tiene que ser la misma: `sortBy` sigue
 * llegando de la URL.
 */
test('una clave no declarada no ordena las funcionalidades', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    // `effective` es una clave real de cada fila, pero no está declarada.
    Livewire::withQueryParams(['sortBy' => 'effective', 'sortDirection' => 'desc'])
        ->test(FeatureManager::class)
        ->assertOk()
        ->assertSeeHtmlInOrder(['api_access', 'export', 'max_projects']);
});

test('el filtro de ámbito separa las del plan de las de la cuenta', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class);

    // Sin override, todas vienen del plan.
    $componente->set('filterScope', 'global')->assertSee('api_access');

    $componente->set('filterScope', 'account')->assertDontSee('api_access');
});

test('limpiar filtros también suelta el ámbito', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(FeatureManager::class)
        ->set('search', 'api')
        ->set('filterScope', 'account')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterScope', '')
        ->assertViewHas('hasActiveFilters', false);
});

/**
 * El catálogo lo define el plan, así que no crece con el uso: esta pantalla no
 * pagina. Y si no pagina, el selector de tamaño de página no manda nada, y
 * enseñarlo sería mentir sobre lo que hace.
 */
test('la pantalla de funcionalidades no ofrece tamaño de página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(FeatureManager::class)
        ->assertOk()
        ->assertDontSeeHtml('wire:model.live="perPage"');
});

test('el vacío por búsqueda de funcionalidades no usa la copia del vacío inicial', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(FeatureManager::class)
        ->set('search', 'no-existe-esta-funcionalidad')
        ->assertSee(__('base-tenant::common.empty_search'))
        ->assertDontSee(__('base-tenant::features.empty'));
});

test('editar y restablecer una funcionalidad siguen funcionando', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class)
        ->call('edit', 'max_users')
        ->assertSet('editing', 'max_users');

    $componente->set('value', '42')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', '');

    // Ahora esa fila es un override de la cuenta, no del plan.
    $componente->set('filterScope', 'account')->assertSee('max_users');

    $componente->call('resetToPlan', 'max_users')
        ->set('filterScope', 'account')
        ->assertDontSee('max_users');
});

/**
 * Una cabecera con más columnas que celdas pinta una tabla torcida sin que nada
 * falle. Se cuentan las dos y se comparan.
 */
test('la tabla de funcionalidades pinta tantas celdas como columnas declara', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(FeatureManager::class)->html();

    preg_match('/<thead.*?<\/thead>/s', $html, $cabecera);
    // Anclado al cuerpo real: el primer `<tbody` de la tabla es el del
    // esqueleto de carga, y medirlo aquí dejaría sin comprobar la fila que se
    // ve el resto del tiempo.
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($cabecera)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    // Sin overrides no hay caducidades que mostrar, así que esa columna no se
    // dibuja: cuatro y cuatro. Con el espacio en `<th ` porque sin él la
    // etiqueta `<thead` también contaría.
    expect(substr_count($cabecera[0], '<th '))->toBe(4)
        ->and(substr_count($primeraFila[0], '<td '))->toBe(4);
});

/**
 * Una columna entera de guiones ocupa ancho y no informa: la de caducidad solo
 * aparece cuando alguna fila tiene algo que poner en ella. Lo que no puede
 * pasar es que aparezca en la cabecera y no en el cuerpo, o al revés.
 */
test('la columna de caducidad aparece en cuanto hay un override', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class)
        ->call('edit', 'max_users')
        ->set('value', '99')
        ->call('save');

    preg_match('/<thead.*?<\/thead>/s', $componente->html(), $cabecera);
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $componente->html(), $primeraFila);

    expect(substr_count($cabecera[0], '<th '))->toBe(5)
        ->and(substr_count($primeraFila[0], '<td '))->toBe(5);
});

/**
 * El esqueleto se pinta con la tabla ya montada, así que una fila con menos
 * celdas que la real endereza la tabla al desaparecer y la tuerce al aparecer.
 * Aquí además la columna de caducidad es condicional, así que se comprueban los
 * dos estados: sin sobrescrituras y con una puesta.
 */
test('el esqueleto de carga de funcionalidades pinta tantas celdas como una fila real', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class);

    $contar = function (string $html): array {
        preg_match('/<tbody wire:loading\.delay.*?<tr.*?<\/tr>/s', $html, $esqueleto);
        preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

        expect($esqueleto)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

        return [substr_count($esqueleto[0], '<td '), substr_count($primeraFila[0], '<td ')];
    };

    [$celdasEsqueleto, $celdasFila] = $contar($componente->html());

    expect($celdasEsqueleto)->toBe(4)->and($celdasFila)->toBe($celdasEsqueleto);

    $componente->call('edit', 'max_users')
        ->set('value', '99')
        ->call('save');

    [$celdasEsqueleto, $celdasFila] = $contar($componente->html());

    expect($celdasEsqueleto)->toBe(5)->and($celdasFila)->toBe($celdasEsqueleto);
});

/**
 * El estado de una funcionalidad booleana se lee de un vistazo por el punto,
 * no por una insignia. `data-feature-state` da el asidero estable: las palabras
 * «Activada» y «Desactivada» conviven en la misma página.
 */
test('una funcionalidad booleana se pinta como estado, no como insignia', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class)->set('search', 'api_access');

    $componente->assertSeeHtml('data-feature-state="disabled"');

    $componente->call('toggle', 'api_access')
        ->assertSeeHtml('data-feature-state="enabled"');
});

/**
 * Una sobrescritura con fecha se apaga sola: llegado el día la cuenta vuelve al
 * plan sin que nadie toque nada. Eso no se ve recorriendo la tabla, así que se
 * anuncia arriba.
 */
test('la tira de contexto avisa de las sobrescrituras con fecha de fin', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class);

    expect($componente->viewData('summary')['expiring'])->toBe(0);

    $componente->call('edit', 'max_users')
        ->set('value', '42')
        ->set('expiresAt', now()->addMonth()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($componente->viewData('summary'))
        ->toMatchArray(['overridden' => 1, 'expiring' => 1]);

    $componente->assertSee(trans_choice('base-tenant::features.expiring_summary', 1, ['count' => 1]));
});

/**
 * Con una sobrescritura puesta, el valor efectivo es la mitad de la historia:
 * lo que dice el plan tiene que seguir a la vista para saber a qué se vuelve.
 */
test('una funcionalidad sobrescrita sigue enseñando lo que dice el plan', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class)
        ->call('edit', 'max_users')
        ->set('value', '42')
        ->call('save')
        ->set('search', 'max_users');

    $componente->assertSee('42')
        ->assertSee(__('base-tenant::features.plan_says'));
});

test('el recuento de la cabecera cuenta el catálogo entero, no lo filtrado', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(FeatureManager::class);

    $total = $componente->viewData('summary')['total'];

    expect($total)->toBeGreaterThan(1);

    $componente->set('search', 'api_access');

    expect($componente->viewData('summary')['total'])->toBe($total)
        ->and($componente->viewData('features')->count())->toBe(1);
});
