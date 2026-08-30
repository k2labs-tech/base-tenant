<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Una vista que no referencia nadie no se borra sola: se queda, alguien la
 * edita por error, y el duplicado de `livewire/pages/auth/` demuestra hasta
 * dónde llega la confusión.
 *
 * La comprobación exige que la referencia termine donde termina el nombre.
 * Buscar el nombre como subcadena daba por viva cualquier vista cuyo nombre
 * fuese prefijo de otra: `dropdown` sobrevivía escondido dentro de
 * `dropdown-link`, y `layouts.navigation` dentro de `layouts.navigation-items`.
 *
 * Borrar una vista puede destapar la siguiente del encadenado: si sólo la
 * referenciaba otra vista muerta, no aparece hasta que esa desaparece. Tras
 * limpiar, vuelve a ejecutar esta prueba hasta que salga en verde; una sola
 * pasada no basta.
 */
test('no hay vistas sin referenciar', function () {
    $raiz = dirname(__DIR__, 2);

    $pajar = '';

    foreach (['src', 'resources/views', 'routes', 'config', 'stubs', 'database'] as $directorio) {
        foreach (File::allFiles("{$raiz}/{$directorio}") as $fichero) {
            if (preg_match('/\.(php|stub)$/', $fichero->getFilename())) {
                $pajar .= File::get($fichero->getPathname())."\n";
            }
        }
    }

    // Vistas que nadie referencia aquí dentro y aun así siguen vivas. El
    // paquete no publica sus vistas, así que la aplicación anfitriona sólo
    // puede llegar a ellas con `view('base-tenant::…')`, una llamada que vive
    // fuera de este repositorio y que esta prueba no puede ver. Para ellas la
    // ausencia de referencias no demuestra nada.
    $permitidas = [
        // Portada pensada para que la renderice la aplicación anfitriona; sus
        // traducciones (`lang/*/welcome.php`) sí se publican.
        'welcome.blade.php',
    ];

    $huerfanas = [];

    foreach (File::allFiles("{$raiz}/resources/views") as $fichero) {
        if (! str_ends_with($fichero->getFilename(), '.blade.php')) {
            continue;
        }

        $relativa = $fichero->getRelativePathname();

        if (in_array($relativa, $permitidas, true)) {
            continue;
        }

        $nombre = str_replace(['/', '.blade.php'], ['.', ''], $relativa);

        // Cómo puede referenciarse: por nombre de vista, como etiqueta de
        // componente, o por el nombre corto que registra Livewire.
        $patrones = [
            // Nombre de vista entre comillas o tras el separador de espacio de nombres.
            '/(?:base-tenant::|[\'"])'.preg_quote($nombre, '/').'(?![a-zA-Z0-9_.-])/',
        ];

        if (str_starts_with($nombre, 'components.')) {
            $componente = substr($nombre, strlen('components.'));
            $patrones[] = '/x-base-tenant::'.preg_quote($componente, '/').'(?![a-zA-Z0-9_-])/';
        }

        if (str_starts_with($nombre, 'livewire.')) {
            $corto = substr($nombre, strlen('livewire.'));
            $patrones[] = '/[\'"]'.preg_quote($corto, '/').'(?![a-zA-Z0-9_.-])/';
            // Etiqueta `<livewire:base-tenant.…>`: hoy todos los componentes
            // nombran su vista con `view()`, pero uno que se apoye en la
            // resolución por convención sólo se vería así.
            $patrones[] = '/<livewire:base-tenant\.'.preg_quote($corto, '/').'(?![a-zA-Z0-9_.-])/';
        }

        $referenciada = false;

        foreach ($patrones as $patron) {
            if (preg_match($patron, $pajar) === 1) {
                $referenciada = true;
                break;
            }
        }

        if (! $referenciada) {
            $huerfanas[] = $relativa;
        }
    }

    sort($huerfanas);

    expect($huerfanas)->toBe([]);
});
