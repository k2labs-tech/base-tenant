<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;

/**
 * La navegación es lo único del armazón que depende de datos: si el mapeo de
 * `Menu::tree()` a `flux:sidebar.nav` se rompe, el usuario se queda sin menú y
 * ninguna otra prueba se entera.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // layout revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
    Menu::sync();
});

test('la barra lateral lista las entradas que el usuario puede ver', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $this->get(route('base-tenant.dashboard'))
        ->assertOk()
        ->assertSee('data-flux-sidebar-nav', false)
        ->assertSee(__('base-tenant::app.navigation.users'))
        ->assertSee(route('base-tenant.users.index'));
});

test('no lista lo que el permiso no alcanza', function () {
    $cuenta = $this->createAccount();
    $viewer = $this->createUser($cuenta, 'customer-viewer');

    $this->actingAsTenant($viewer, $cuenta);

    $this->get(route('base-tenant.dashboard'))
        ->assertOk()
        ->assertDontSee(route('base-tenant.users.index'));
});

test('cada sección conserva su icono para cuando la barra se pliega', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $html = $this->get(route('base-tenant.dashboard'))->assertOk()->getContent();

    // `flux:sidebar.group` sólo emite este desplegable —lo único que se ve con
    // la barra plegada— cuando el grupo trae icono. Si alguien quita el icono
    // de una sección, el desplegable desaparece con él y la barra plegada se
    // queda en blanco: contarlos es la forma de que eso falle a gritos.
    $secciones = substr_count($html, 'data-flux-sidebar-group-dropdown="');
    $disclosures = substr_count($html, 'data-flux-sidebar-group>');

    expect($secciones)->toBe(2)
        ->and($disclosures)->toBe($secciones);

    // Y los iconos que se han elegido son los que se pintan de verdad.
    expect($html)
        ->toContain('M3.75 6A2.25 2.25 0 0 1 6 3.75')   // squares-2x2, sección «main»
        ->toContain('M9.594 3.94c.09-.542.56-.94 1.11-.94'); // cog-6-tooth, sección «settings»
});

test('una entrada con hijos y sin icono cae en el icono de reserva', function () {
    $entrada = [
        'title' => 'Informes',
        'icon' => null,
        'active' => false,
        'href' => null,
        'badge' => null,
        'target' => null,
        'children' => [
            [
                'title' => 'Ventas',
                'icon' => null,
                'active' => false,
                'href' => '/informes/ventas',
                'badge' => null,
                'target' => null,
                'children' => [],
            ],
        ],
    ];

    $html = view('base-tenant::layouts.navigation-item', ['item' => $entrada])->render();

    // Sin icono de reserva, `flux:sidebar.group` no emitiría desplegable y el
    // grupo entero se volvería invisible al plegar la barra.
    expect($html)
        ->toContain('data-flux-sidebar-group-dropdown="')
        ->toContain('Informes')
        ->toContain('/informes/ventas');
});
