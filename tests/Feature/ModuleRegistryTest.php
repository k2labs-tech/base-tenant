<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Support\Module;

/**
 * Un módulo declarado en la lista pero sin sección de configuración se lee como
 * apagado en toda instalación, y el interruptor no aparece por ningún lado. Es
 * el fallo más silencioso posible, así que se comprueban las dos direcciones.
 */
test('cada módulo declarado tiene su sección de configuración con interruptor', function () {
    foreach (Module::all() as $modulo) {
        expect(config("base-tenant.{$modulo}"))
            ->toBeArray("El módulo `{$modulo}` no tiene sección de configuración.")
            ->toHaveKey('enabled');
    }
});

test('la lista de módulos no tiene duplicados', function () {
    expect(Module::all())->toBe(array_values(array_unique(Module::all())));
});

test('un módulo apagado no se da por activo', function () {
    config(['base-tenant.files.enabled' => false]);

    expect(Module::enabled(Module::FILES))->toBeFalse()
        ->and(Module::active())->not->toContain(Module::FILES);

    config(['base-tenant.files.enabled' => true]);

    expect(Module::enabled(Module::FILES))->toBeTrue()
        ->and(Module::active())->toContain(Module::FILES);
});

/**
 * Las tablas de un módulo apagado pueden no existir. Fallar en la puerta de
 * entrada da un mensaje que dice qué interruptor tocar; dejar pasar la llamada
 * da un error de SQL a tres capas de distancia de la causa.
 */
test('entrar en un módulo apagado falla nombrando la variable de entorno', function () {
    config(['base-tenant.metering.enabled' => false]);

    expect(fn () => Module::ensure(Module::METERING))
        ->toThrow(ModuleDisabledException::class, 'BASE_TENANT_METERING_ENABLED');
});

test('entrar en un módulo activo no interrumpe nada', function () {
    config(['base-tenant.metering.enabled' => true]);

    Module::ensure(Module::METERING);
})->throwsNoExceptions();

/**
 * Un módulo viene encendido cuando su código existe y apagado mientras no.
 * Un interruptor encendido sin nada detrás dice que hay algo que no hay, y el
 * fallo aparece a tres capas de distancia de la configuración que lo causó.
 *
 * Al construir un módulo, su entrada baja aquí y sube su valor por defecto.
 */
test('cada módulo viene encendido si y solo si está construido', function () {
    // La pre-venta se construyó pero sigue apagada a propósito: encenderla
    // cierra el registro estándar, y eso no puede pasar por actualizar.
    $construidos = [
        Module::METERING,
        Module::FILES,
        Module::LANGUAGES,
        Module::SOCIAL,
        Module::SEQUENCES,
        Module::TRANSFER,
        Module::CONNECTIONS,
        Module::WEBHOOKS,
        Module::ONBOARDING,
        Module::SUPPRESSIONS,
        Module::GDPR,
    ];

    foreach (Module::all() as $modulo) {
        expect(config("base-tenant.{$modulo}.enabled"))->toBe(
            in_array($modulo, $construidos, true),
            "El módulo `{$modulo}` no viene en el estado que le corresponde."
        );
    }
});

/**
 * La pre-venta seguirá apagada también cuando se construya: encenderla cierra
 * el registro estándar, que no es un efecto que deba ocurrir por defecto.
 */
test('la pre-venta viene apagada', function () {
    expect(config('base-tenant.presale.enabled'))->toBeFalse();
});
