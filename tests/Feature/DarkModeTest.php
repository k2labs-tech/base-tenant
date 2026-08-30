<?php

declare(strict_types=1);

/**
 * El modo oscuro se rompe de una forma que ninguna prueba de render detecta: un
 * elemento se queda con su color claro y sólo se ve al mirar la página en
 * oscuro. Esta guarda recorre TODAS las vistas del paquete y exige que toda
 * superficie clara lleve su pareja `dark:` DENTRO DEL MISMO ámbito de clases,
 * no en cualquier punto del fichero: una comprobación por fichero daría por
 * buena una vista donde un elemento está emparejado y el de al lado no, que es
 * justo el fallo que esto persigue.
 *
 * Los atributos se buscan sobre el fichero ENTERO, no línea a línea. Hay seis
 * atributos `class="..."` cuyas comillas abarcan varias líneas (los ternarios
 * de `account-settings`, `navigation-manager` y `feature-manager`): una versión
 * que recorriese líneas sueltas nunca vería esos atributos y los daría por
 * buenos en silencio, que es exactamente lo que pasó hasta ahora.
 *
 * Un «ámbito» es cada lista de clases que el navegador acaba viendo junta:
 *
 * - El texto literal de un `class="..."`, con las interpolaciones Blade fuera.
 * - Cada literal entrecomillado dentro de un `{{ ... }}`, por separado: las dos
 *   ramas de un ternario son excluyentes, así que la pareja de una NO puede dar
 *   por buena la otra.
 * - El valor de un `'class' => '...'` de `$attributes->merge()`, que es un
 *   atributo de clases aunque no lo parezca sintácticamente.
 *
 * La pareja se busca por FAMILIA (`bg-`, `text-`, `border-`, `divide-`) y por
 * CADENA DE VARIANTES, no por valor exacto, por dos motivos:
 *
 * - Hay parejas afinadas a mano que no coinciden con la tabla del rediseño y
 *   son correctas: `components/modal` usa `dark:bg-zinc-800` sobre `bg-white`
 *   para separarse del fondo, y `components/input-label` usa
 *   `dark:text-zinc-300` sobre `text-zinc-700`. Exigir el valor de la tabla
 *   obligaría a estropearlas.
 * - Exigir la misma cadena de variantes evita que un `dark:bg-*` suelto dé por
 *   emparejado un `hover:bg-*`: cada estado necesita su propia pareja.
 *
 * Hay dos tablas. La base deriva la variante del token (`hover:bg-zinc-100` =>
 * `dark:hover:bg-zinc-800`), y la de EXCEPCIONES manda cuando ambas aplican,
 * porque en oscuro el hover debe tirar hacia el lado claro y no repetir la
 * dirección del tema claro: `hover:bg-zinc-50` pide `dark:hover:bg-zinc-800`
 * (aclara sobre el `dark:bg-zinc-900` en el que se apoya) y no el
 * `dark:hover:bg-zinc-950` que la tabla base habría derivado. Por lo mismo los
 * tres tonos oscuros de estado (700, 800 y 900) caen todos en el mismo `300`:
 * sobre fondo oscuro son «la versión más brillante del color en reposo», y
 * distinguirlos ahí no se vería.
 *
 * Quedan fuera a propósito:
 *
 * - `text-white`, `bg-accent-600`, `bg-zinc-600` y `bg-zinc-900`: elementos de
 *   color sólido (logotipos, botones de acento, insignias) que ya se leen igual
 *   en ambos temas; emparejarlos los rompería.
 * - `bg-success`, `text-success` y compañía: los tokens planos de estado que
 *   define `resources/css`, que ya resuelven su propio color por tema.
 * - Los tonos `-500` de estado (`bg-danger-500`, `bg-warning-500`,
 *   `bg-info-500`) que marcan la prioridad de una notificación: son puntos de
 *   color sólido sobre la tarjeta, legibles en ambos temas.
 * - `livewire/two-factor-authentication.blade.php` línea 58: el `bg-white` que
 *   envuelve el SVG del código QR. El QR es negro sobre transparente; si ese
 *   fondo se oscurece, el código pierde contraste y deja de poder escanearse.
 *   Es la única excepción por línea, y va aquí para que se vea.
 *
 * Todo lo que antes vivía en el ámbito del fichero (dos funciones y dos
 * constantes globales) está ahora dentro del closure: Pest carga todos los
 * ficheros de prueba en un mismo proceso, y una constante sin espacio de
 * nombres que choque con la de otro fichero conserva en silencio la primera
 * definición, avisando sólo con un warning que `phpunit.xml` no escala a error.
 */
test('cada superficie clara lleva su pareja oscura en el mismo ámbito de clases', function (string $vista) {
    $estados = ['success', 'warning', 'danger', 'info'];

    /** El vocabulario de superficies del rediseño: claro => su pareja oscura. */
    $parejas = [
        'bg-white' => 'dark:bg-zinc-900',
        'bg-zinc-50' => 'dark:bg-zinc-950',
        'bg-zinc-100' => 'dark:bg-zinc-800',
        'text-zinc-900' => 'dark:text-white',
        'text-zinc-800' => 'dark:text-zinc-100',
        'text-zinc-700' => 'dark:text-zinc-200',
        'text-zinc-600' => 'dark:text-zinc-300',
        'text-zinc-500' => 'dark:text-zinc-400',
        'text-zinc-400' => 'dark:text-zinc-500',
        'border-zinc-100' => 'dark:border-zinc-800',
        'border-zinc-200' => 'dark:border-zinc-700',
        'border-zinc-300' => 'dark:border-zinc-600',
        'divide-zinc-200' => 'dark:divide-zinc-700',
        'bg-accent-50' => 'dark:bg-accent-950/40',
        'bg-accent-100' => 'dark:bg-accent-900/40',
        'text-accent-600' => 'dark:text-accent-400',
        'text-accent-700' => 'dark:text-accent-300',
    ];

    foreach ($estados as $estado) {
        $parejas["bg-{$estado}-100"] = "dark:bg-{$estado}-900/30";
        $parejas["text-{$estado}-600"] = "dark:text-{$estado}-400";
        $parejas["text-{$estado}-800"] = "dark:text-{$estado}-300";
        $parejas["border-{$estado}-200"] = "dark:border-{$estado}-800";
    }

    /** Excepciones por token completo; mandan sobre la tabla base. */
    $excepciones = [
        'hover:bg-zinc-50' => 'dark:hover:bg-zinc-800',
        'hover:bg-zinc-200' => 'dark:hover:bg-zinc-700',
        'focus:bg-zinc-100' => 'dark:focus:bg-zinc-800',
        'focus:bg-zinc-200' => 'dark:focus:bg-zinc-700',
        'hover:border-zinc-400' => 'dark:hover:border-zinc-500',
        'hover:text-accent-800' => 'dark:hover:text-accent-300',
    ];

    foreach ($estados as $estado) {
        foreach ([700, 800, 900] as $tono) {
            $excepciones["hover:text-{$estado}-{$tono}"] = "dark:hover:text-{$estado}-300";
        }
    }

    /** Tokens ya oscuros o de color sólido: pedirles pareja los estropearía. */
    $exentos = [
        'text-white',
        'bg-accent-600',
        'bg-zinc-600',
        'bg-zinc-900',
        'bg-success',
        'text-success',
        'bg-warning',
        'text-warning',
        'bg-danger',
        'text-danger',
        'bg-info',
        'text-info',
    ];

    /** Excepciones razonadas línea a línea; el motivo está en el docblock. */
    $exentasPorLinea = [
        'livewire/two-factor-authentication.blade.php' => [58],
    ];

    /** Parte un token en [cadena de variantes, utilidad base]. */
    $partir = function (string $token): ?array {
        if ($token === '' || str_contains($token, '[')) {
            return null;
        }

        $partes = explode(':', $token);
        $base = array_pop($partes);

        return [$partes, $base];
    };

    /** La familia de propiedad de una utilidad, o null si no nos interesa. */
    $familia = function (string $base): ?string {
        foreach (['bg-', 'text-', 'border-', 'divide-'] as $prefijo) {
            if (str_starts_with($base, $prefijo)) {
                return $prefijo;
            }
        }

        return null;
    };

    /** Clave de supresión: cadena de variantes (sin `dark`) + familia. */
    $clave = function (array $variantes, string $base) use ($familia): ?string {
        $prefijo = $familia($base);

        if ($prefijo === null) {
            return null;
        }

        $resto = array_values(array_diff($variantes, ['dark']));
        sort($resto);

        return implode(':', $resto).'|'.$prefijo;
    };

    /** La pareja que exige un token, mirando primero las excepciones. */
    $parejaDe = function (string $token) use ($partir, $parejas, $excepciones, $exentos): ?string {
        if (isset($excepciones[$token])) {
            return $excepciones[$token];
        }

        $partido = $partir($token);

        if ($partido === null) {
            return null;
        }

        [$variantes, $base] = $partido;

        if (in_array('dark', $variantes, true) || in_array($base, $exentos, true)) {
            return null;
        }

        if (! isset($parejas[$base])) {
            return null;
        }

        if ($variantes === []) {
            return $parejas[$base];
        }

        return 'dark:'.implode(':', $variantes).':'.substr($parejas[$base], strlen('dark:'));
    };

    /**
     * Los ámbitos de clases de un valor de atributo: el texto literal por un
     * lado y cada literal entrecomillado de las interpolaciones por otro.
     *
     * @return array<int, string>
     */
    $ambitosDelValor = function (string $valor): array {
        $trozos = preg_split('/(\{\{.*?\}\}|\{!!.*?!!\})/s', $valor, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $literal = '';
        $ambitos = [];

        foreach ($trozos as $indice => $trozo) {
            if ($indice % 2 === 0) {
                $literal .= ' '.$trozo;

                continue;
            }

            preg_match_all("/'([^']*)'/", $trozo, $encontrados);

            foreach ($encontrados[1] as $rama) {
                $ambitos[] = $rama;
            }
        }

        array_unshift($ambitos, $literal);

        return $ambitos;
    };

    $ruta = __DIR__.'/../../resources/views/'.$vista;

    expect($ruta)->toBeReadableFile();

    $contenido = (string) file_get_contents($ruta);
    $lineasExentas = $exentasPorLinea[$vista] ?? [];
    $fallos = [];

    // Los dos envoltorios de una lista de clases, buscados sobre el fichero
    // entero para no perder los atributos que abarcan varias líneas.
    $atributos = [];

    foreach (['/(?<![:\w-])class="([^"]*)"/', "/'class'\s*=>\s*'([^']*)'/"] as $patron) {
        preg_match_all($patron, $contenido, $encontrados, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($encontrados as $encontrado) {
            $atributos[] = [
                'valor' => $encontrado[1][0],
                'linea' => substr_count(substr($contenido, 0, $encontrado[0][1]), "\n") + 1,
            ];
        }
    }

    foreach ($atributos as $atributo) {
        if (in_array($atributo['linea'], $lineasExentas, true)) {
            continue;
        }

        foreach ($ambitosDelValor($atributo['valor']) as $ambito) {
            $tokens = preg_split('/\s+/', trim($ambito), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            // Qué combinaciones de variantes + familia ya traen un dark:.
            $yaOscuros = [];

            foreach ($tokens as $token) {
                $partido = $partir($token);

                if ($partido === null) {
                    continue;
                }

                [$variantes, $base] = $partido;

                if (! in_array('dark', $variantes, true)) {
                    continue;
                }

                $llave = $clave($variantes, $base);

                if ($llave !== null) {
                    $yaOscuros[$llave] = true;
                }
            }

            foreach ($tokens as $token) {
                $esperada = $parejaDe($token);

                if ($esperada === null) {
                    continue;
                }

                [$variantes, $base] = $partir($token);
                $llave = $clave($variantes, $base);

                if ($llave === null || isset($yaOscuros[$llave])) {
                    continue;
                }

                $fallos[] = sprintf('línea %d: «%s» sin «%s»', $atributo['linea'], $token, $esperada);
            }
        }
    }

    expect($fallos)->toBe([], $vista.' deja superficies claras sin pareja oscura -> '.implode(' | ', $fallos));
})->with(function (): array {
    $raiz = __DIR__.'/../../resources/views';
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    $vistas = [];

    foreach ($iterador as $fichero) {
        if ($fichero->isFile() && str_ends_with($fichero->getFilename(), '.blade.php')) {
            $vistas[] = substr($fichero->getPathname(), strlen($raiz) + 1);
        }
    }

    sort($vistas);

    return $vistas;
});
