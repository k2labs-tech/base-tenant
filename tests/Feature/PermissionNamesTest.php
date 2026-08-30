<?php

declare(strict_types=1);

/**
 * Los nombres de permiso se piden con una clave construida
 * (`__('base-tenant::permissions.names.'.$permission->name)`), así que
 * `TranslationKeysTest` los salta: no puede resolver el último tramo leyendo
 * el código.
 *
 * Ese hueco costó que la pantalla de roles enseñara la clave en crudo desde
 * siempre, en el paquete y en toda instalación. Esto lo cierra: cada permiso
 * declarado tiene que tener nombre, en los dos idiomas.
 */
test('cada permiso declarado tiene nombre traducido', function (string $idioma) {
    app()->setLocale($idioma);

    $faltan = [];

    foreach (collect(config('base-tenant.permissions'))->flatten() as $permiso) {
        $clave = 'base-tenant::permissions.names.'.$permiso;

        if (__($clave) === $clave) {
            $faltan[] = $permiso;
        }
    }

    expect($faltan)->toBe([], "Sin nombre en `{$idioma}`: ".implode(', ', $faltan));
})->with(['en', 'es']);

/**
 * Y cada grupo, que es la cabecera bajo la que se listan.
 */
test('cada grupo de permisos tiene nombre traducido', function (string $idioma) {
    app()->setLocale($idioma);

    $faltan = [];

    foreach (array_keys(config('base-tenant.permissions')) as $grupo) {
        $clave = 'base-tenant::permissions.groups.'.$grupo;

        if (__($clave) === $clave) {
            $faltan[] = $grupo;
        }
    }

    expect($faltan)->toBe([], "Sin nombre de grupo en `{$idioma}`: ".implode(', ', $faltan));
})->with(['en', 'es']);
