<?php

declare(strict_types=1);

beforeEach(function () {
    $this->withoutVite();
    $this->syncPermissions();
});

/**
 * `HasFeature` manda aquí cuando al plan le falta una feature, y la ruta de
 * facturación sólo existe con las suscripciones activadas: la pantalla de
 * "mejora tu plan" devolvía 500 justo en las instalaciones que venden sus
 * planes fuera del producto.
 */
test('la pantalla de mejora carga sin la ruta de facturación', function () {
    expect(app('router')->has('base-tenant.billing'))->toBeFalse();

    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta)
        ->get(route('base-tenant.upgrade'))
        ->assertOk()
        ->assertSee(__('base-tenant::plans.upgrade_contact'))
        ->assertDontSee(__('base-tenant::plans.upgrade_action'));
});

/**
 * Flux resuelve sus componentes con su propio path anónimo, así que
 * `x-dynamic-component` sólo los encuentra por el nombre de vista del
 * namespace: `flux::date-picker`, con dos puntos dobles. Con la forma de la
 * etiqueta —`flux:date-picker`— no resuelve nada, y la pantalla reventaba
 * precisamente donde Flux Pro sí está instalado.
 */
test('los componentes de Flux resueltos en runtime usan el nombre del namespace', function () {
    $vistas = [
        dirname(__DIR__, 2).'/resources/views/livewire/activity-log.blade.php',
    ];

    foreach (glob(dirname(__DIR__, 2).'/resources/views/**/*.blade.php') ?: [] as $vista) {
        $vistas[] = $vista;
    }

    foreach (array_unique($vistas) as $vista) {
        $contenido = file_get_contents($vista);

        expect($contenido)->not->toMatch('/component="flux:[a-z]/');
    }
});

test('el selector de fechas de Pro se resuelve por nombre de vista', function () {
    $contenido = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/activity-log.blade.php');

    expect($contenido)->toContain('component="flux::date-picker"');
});
