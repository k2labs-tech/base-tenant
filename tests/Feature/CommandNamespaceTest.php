<?php

declare(strict_types=1);

use Base\Tenant\Console\Commands\SyncMenusCommand;
use Illuminate\Support\Facades\Artisan;

/**
 * La convención de la v2 es que todo comando del paquete vive bajo
 * `k2labs-base:`. Un comando nuevo escrito con el prefijo viejo pasaría
 * inadvertido para siempre, así que la lista se comprueba entera.
 */
test('todos los comandos del paquete viven bajo k2labs-base', function () {
    $propios = collect(Artisan::all())
        ->filter(fn ($comando) => str_starts_with($comando::class, 'Base\\Tenant\\'));

    expect($propios)->not->toBeEmpty();

    $propios->each(function ($comando) {
        expect($comando->getName())->toStartWith('k2labs-base:');
    });
});

/**
 * El nombre v1 tiene que seguir resolviendo al mismo comando: los scripts de
 * despliegue de los proyectos host lo tienen escrito.
 */
test('cada comando conserva su nombre v1 como alias', function () {
    $propios = collect(Artisan::all())
        ->filter(fn ($comando) => str_starts_with($comando::class, 'Base\\Tenant\\'));

    $propios->each(function ($comando) {
        $viejo = str_replace('k2labs-base:', 'base-tenant:', (string) $comando->getName());

        expect($comando->getAliases())->toContain($viejo);
    });
});

test('el nombre viejo ejecuta el comando y avisa de la deprecación', function () {
    $codigo = Artisan::call('base-tenant:sync-menus');

    // `output()` vacía el buffer al leerlo: una segunda llamada devuelve ''.
    $salida = Artisan::output();

    expect($codigo)->toBe(0)
        ->and($salida)->toContain('base-tenant:sync-menus')
        ->and($salida)->toContain('k2labs-base:sync-menus');
});

/**
 * El aviso solo tiene sentido cuando se ha usado el nombre viejo. Si saliera
 * siempre, dejaría de significar nada y todo el mundo aprendería a ignorarlo.
 */
test('el nombre nuevo no avisa de nada', function () {
    Artisan::call('k2labs-base:sync-menus');

    $salida = Artisan::output();

    // Sin este primer asidero el resto pasaría también con la salida vacía.
    expect($salida)->toContain('Syncing navigation menus')
        ->and($salida)->not->toContain('base-tenant:sync-menus');
});

test('el alias resuelve a la misma clase, no a una copia', function () {
    expect(Artisan::all()['base-tenant:sync-menus'] ?? Artisan::all()['k2labs-base:sync-menus'])
        ->toBeInstanceOf(SyncMenusCommand::class);
});
