<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Una clave usada en una vista pero ausente del fichero de idioma se pinta en
 * pantalla tal cual, sin avisar: el registro de actividad entero se publicó
 * así, con `activity.php` sin crear en ninguno de los dos idiomas.
 *
 * Solo claves literales: las que se construyen concatenando
 * (`__('base-tenant::permissions.names.'.$permission->name)`) dependen de datos
 * y no se pueden resolver leyendo el código.
 */
test('toda clave de traducción existe en inglés y en español', function (string $idioma) {
    $raiz = dirname(__DIR__, 2);

    // Definida dentro del test a propósito: Pest carga todos los ficheros de
    // prueba en el mismo proceso, así que una función a nivel de fichero puede
    // chocar con otra del mismo nombre y quedarse con la primera definición.
    $clavesUsadas = function () use ($raiz): array {
        $claves = [];

        foreach ([$raiz.'/src', $raiz.'/resources/views'] as $directorio) {
            foreach (File::allFiles($directorio) as $fichero) {
                if (! preg_match('/\.(php|blade\.php)$/', $fichero->getFilename())) {
                    continue;
                }

                preg_match_all(
                    "/base-tenant::([a-z0-9_-]+)\.([A-Za-z0-9_.-]+?)(?='|\")/",
                    File::get($fichero->getPathname()),
                    $coincidencias,
                    PREG_SET_ORDER
                );

                foreach ($coincidencias as $coincidencia) {
                    $claves[$coincidencia[1].'.'.$coincidencia[2]] = true;
                }
            }
        }

        return array_keys($claves);
    };

    $faltan = [];

    foreach ($clavesUsadas() as $clave) {
        // Una clave acabada en punto es el prefijo de una concatenación
        // (`__('base-tenant::features.names.'.$feature->name)`): el segmento
        // final lo pone un dato en tiempo de ejecución, no se puede resolver.
        if (str_ends_with($clave, '.')) {
            continue;
        }

        [$fichero, $resto] = explode('.', $clave, 2);

        // `layouts.`, `livewire.`, `components.` y `errors.` son directorios de
        // vistas, no ficheros de idioma: comparten el prefijo `base-tenant::`
        // porque el namespace es el mismo para vistas y traducciones.
        if (in_array($fichero, ['layouts', 'livewire', 'components', 'errors'], true)) {
            continue;
        }

        $ruta = "{$raiz}/resources/lang/{$idioma}/{$fichero}.php";

        if (! File::exists($ruta)) {
            $faltan[] = "{$clave} (no existe {$idioma}/{$fichero}.php)";

            continue;
        }

        $valor = include $ruta;

        foreach (explode('.', $resto) as $segmento) {
            if (! is_array($valor) || ! array_key_exists($segmento, $valor)) {
                $valor = null;
                break;
            }

            $valor = $valor[$segmento];
        }

        if ($valor === null) {
            $faltan[] = $clave;
        }
    }

    expect($faltan)->toBe([]);
})->with(['en', 'es']);
