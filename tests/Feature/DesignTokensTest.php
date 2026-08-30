<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Las escalas que el paquete aporta. Flux consume `zinc` (paleta por defecto de
 * Tailwind), pero NO aporta escala `accent`: `flux.css` solo declara los tokens
 * planos `--color-accent`, `--color-accent-content` y
 * `--color-accent-foreground`, ninguno con tono. Las cuatro de estado tampoco
 * las cubre. Todas las que las vistas usan con tono salen de este fichero.
 */
test('el tema define las cuatro escalas de estado enteras', function () {
    $tema = File::get(dirname(__DIR__, 2).'/resources/css/base-tenant.css');

    // Extraer el bloque @theme real (no el del encabezado). Usar ancla de inicio
    // de línea y búsqueda perezosa para evitar capturar el de la documentación.
    $patron = '/^@theme\s*\{(.*?)^\}/ms';
    expect(preg_match($patron, $tema, $m))->toBe(1);
    $bloqueTema = $m[1];

    // Guardia de regresión: un `*/` anidado en el encabezado cierra el bloque
    // de comentario de documentación, rompiendo la compilación de todas las
    // aplicaciones anfitrionas. Verificar que el encabezado cierre correctamente.
    $cabecera = substr($tema, 0, strpos($tema, $m[0]));
    expect(substr_count($cabecera, '*/'), 'nested */ in header breaks host builds')->toBe(1);

    // Mapeos esperados: estado → paleta Tailwind
    $mapeos = [
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'info' => 'blue',
    ];

    $tonos = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
    $faltan = [];

    foreach ($mapeos as $estado => $paleta) {
        // Verificar que cada tono esté declarado correctamente
        foreach ($tonos as $tono) {
            $esperado = "--color-{$estado}-{$tono}: var(--color-{$paleta}-{$tono});";
            if (! str_contains($bloqueTema, $esperado)) {
                $faltan[] = $esperado;
            }
        }

        // Las vistas también usan la forma plana (`bg-success`), que hoy
        // inyecta el instalador y pasa a vivir aquí. El valor plano mapea al 500.
        $esperadoPlano = "--color-{$estado}: var(--color-{$paleta}-500);";
        if (! str_contains($bloqueTema, $esperadoPlano)) {
            $faltan[] = $esperadoPlano;
        }
    }

    expect($faltan)->toBe([]);
});

/**
 * La escala que se perdió al retirar la inyección del instalador: 156
 * utilidades de las vistas (`bg-accent-600`, `ring-accent-500`) dependen de
 * ella y Flux no la aporta. Violeta es exactamente lo que el instalador metía
 * en el `@theme` de cada aplicación, así que las instalaciones existentes no
 * ven ningún cambio.
 */
test('el tema define la escala accent entera', function () {
    $tema = File::get(dirname(__DIR__, 2).'/resources/css/base-tenant.css');

    expect(preg_match('/^@theme\s*\{(.*?)^\}/ms', $tema, $m))->toBe(1);
    $bloqueTema = $m[1];

    $faltan = [];

    foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $tono) {
        $esperado = "--color-accent-{$tono}: var(--color-violet-{$tono});";

        if (! str_contains($bloqueTema, $esperado)) {
            $faltan[] = $esperado;
        }
    }

    expect($faltan)->toBe([]);

    // El plano se deja a Flux a propósito: no es un tono, es un token de
    // superficie que `.dark` cambia a blanco. Declararlo aquí ganaría en claro
    // y perdería en oscuro, dando un acento que cambia de color con el tema.
    expect($bloqueTema, 'the flat --color-accent belongs to Flux')
        ->not->toMatch('/--color-accent:\s/');
});

/**
 * El fallo que motivó el rediseño: en Tailwind v4 una clase de una escala que
 * no existe no da error, simplemente no aplica la propiedad. `border` y
 * `divide-y` sí aplican grosor, así que heredan currentColor y los separadores
 * salen casi negros.
 *
 * Se vigilan dos formas distintas, porque fallan por motivos distintos:
 *   A) escala desconocida con tono (`bg-gray-100`), que el tema no declara.
 *   B) token plano retirado sin tono (`shadow-soft`, `text-error`), que el
 *      patrón de tono no puede ver porque exige `\d{2,3}`.
 *
 * También se recorre `src/`: las clases que se construyen en PHP quedan fuera
 * del `@source` de la aplicación anfitriona (que solo cubre `resources/views`),
 * así que Tailwind nunca las genera aunque la escala sí exista.
 */
test('las vistas solo usan escalas de color que el tema define', function () {
    $raiz = dirname(__DIR__, 2);

    // Las permitidas no se listan a mano: se leen del fichero de tema. Una
    // escala en una lista fija que nadie declara es justo el fallo de `accent`,
    // que estuvo permitida y sin definir. Lo único que se da por hecho es lo
    // que trae Tailwind de serie.
    $deTailwind = ['zinc'];

    $tema = File::get($raiz.'/resources/css/base-tenant.css');
    expect(preg_match('/^@theme\s*\{(.*?)^\}/ms', $tema, $m))->toBe(1);

    preg_match_all('/--color-([a-z]+)-500:/', $m[1], $declaradas);
    $delTema = array_values(array_unique($declaradas[1]));

    // Guardia sobre la propia guardia: si el fichero de tema se vacía, esta
    // prueba pasaría por no permitir nada y no encontrar nada que comparar.
    expect($delTema)->toContain('accent', 'success', 'warning', 'danger', 'info');

    $permitidas = [...$deTailwind, ...$delTema];

    $prefijos = 'bg|text|border|ring|divide|outline|shadow|from|via|to|placeholder|decoration|caret|accent|fill|stroke';

    // Sufijos direccionales y de `offset`. `inline` y `block` quedan fuera a
    // propósito: solo aparecen en utilidades de tamaño lógico, nunca de color.
    $sub = '(?:-(?:offset|t|r|b|l|x|y|s|e))?';

    // El lookahead evita que B vuelva a reportar lo que A ya ha cazado.
    $patronEscala = "/\b(?:{$prefijos}){$sub}-([a-z]+)-\d{2,3}\b/";
    $patronPlano = "/\b(?:{$prefijos}){$sub}-(primary|secondary|surface|error|soft)\b(?!-\d)/";

    $infractoras = [];

    foreach (['resources/views', 'src'] as $directorio) {
        foreach (File::allFiles($raiz.'/'.$directorio) as $fichero) {
            if (! str_ends_with($fichero->getFilename(), '.php')) {
                continue;
            }

            $contenido = File::get($fichero->getPathname());
            $ruta = $directorio.'/'.$fichero->getRelativePathname();

            preg_match_all($patronEscala, $contenido, $conTono);

            foreach (array_unique($conTono[1]) as $escala) {
                if (! in_array($escala, $permitidas, true)) {
                    $infractoras[] = $ruta.': escala desconocida `'.$escala.'`';
                }
            }

            preg_match_all($patronPlano, $contenido, $planos);

            foreach (array_unique($planos[1]) as $token) {
                $infractoras[] = $ruta.': token plano retirado `'.$token.'`';
            }
        }
    }

    expect(array_unique($infractoras))->toBe([]);
});
