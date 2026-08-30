<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;

beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // layout revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
    Menu::sync();
});

/**
 * El armazón no tiene lógica propia, pero sí piezas que se pierden en una
 * reescritura sin que nada falle: el conmutador de cuenta, la campana y el
 * banner de suplantación son componentes Livewire que hay que seguir montando.
 */
test('el armazón monta sus piezas', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $respuesta = $this->get(route('base-tenant.dashboard'))->assertOk();

    $respuesta->assertSee('data-flux-sidebar', false);
    $respuesta->assertSee('data-flux-main', false);
    $respuesta->assertSeeLivewire('base-tenant.account-switcher');
    $respuesta->assertSeeLivewire('base-tenant.notification-bell');
    $respuesta->assertSee($usuario->name);
});

/**
 * La pantalla de invitado es el otro armazón del paquete y nadie lo miraba: sin
 * `flux:toast` montado, cualquier aviso que levante el login o el registro se
 * pierde sin dejar rastro.
 */
test('la pantalla de invitado monta el contenedor de avisos', function () {
    $this->get(route('base-tenant.login'))
        ->assertOk()
        ->assertSee('<ui-toast', false)
        ->assertSee('wire:submit="login"', false)
        ->assertSee(__('Log in'))
        ->assertSee('dark:bg-zinc-950', false);
});

test('el selector de tema ofrece los tres modos', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $this->get(route('base-tenant.dashboard'))
        ->assertOk()
        ->assertSee(__('base-tenant::app.appearance.light'))
        ->assertSee(__('base-tenant::app.appearance.dark'))
        ->assertSee(__('base-tenant::app.appearance.system'));
});
