<?php

declare(strict_types=1);

use Base\Tenant\Console\Support\StylesheetManager;

/**
 * El `app.css` que Laravel 12 genera de fábrica: un import de Tailwind, el
 * `@source` de las vistas propias y un `@theme` con la tipografía.
 *
 * Se define como closure y no como constante de fichero: Pest carga todos los
 * tests en el mismo proceso y una constante sin espacio de nombres que choque
 * con la de otro fichero se ignora en silencio (solo emite un warning, y
 * `phpunit.xml` no tiene `failOnWarning`).
 */
$appCss = fn (): string => <<<'CSS'
    @import 'tailwindcss';

    @source '../**/*.blade.php';

    @theme {
        --font-sans: 'Instrument Sans', ui-sans-serif, system-ui;
    }
    CSS;

test('añade el import del tema y las rutas de escaneo', function () use ($appCss) {
    $resultado = (new StylesheetManager)->apply($appCss());

    expect($resultado)
        ->toContain("@import '../../vendor/base/tenant/resources/css/base-tenant.css';")
        ->toContain("@source '../../vendor/base/tenant/resources/views/**/*.blade.php';")
        // El paquete puede estar enlazado durante el desarrollo o instalado en
        // `vendor/`: se declaran las dos rutas.
        ->toContain("@source '../../base-tenant/resources/views/**/*.blade.php';");
});

/**
 * Importado antes que Tailwind, las utilidades del tema no llegan a generarse.
 */
test('el import va después de tailwind', function () use ($appCss) {
    $resultado = (new StylesheetManager)->apply($appCss());

    expect(strpos($resultado, "@import 'tailwindcss';"))
        ->toBeLessThan(strpos($resultado, 'base-tenant.css'));
});

test('aplicarlo dos veces no duplica nada', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $unaVez = $gestor->apply($appCss());
    $dosVeces = $gestor->apply($unaVez);

    expect($dosVeces)->toBe($unaVez)
        ->and(substr_count($dosVeces, 'base-tenant.css'))->toBe(1);
});

test('respeta un app.css que no tiene bloque @theme', function () {
    $resultado = (new StylesheetManager)->apply("@import 'tailwindcss';\n");

    expect($resultado)->toContain('base-tenant.css');
});

/**
 * El instalador puede encontrarse un fichero a medio configurar: con las rutas
 * de escaneo puestas a mano pero sin el import, o al revés. Cada pieza se
 * añade por su cuenta.
 */
test('completa un app.css configurado a medias', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $soloRutas = $gestor->apply($appCss());
    $soloRutas = str_replace(
        "@import '../../vendor/base/tenant/resources/css/base-tenant.css';\n",
        '',
        $soloRutas
    );

    expect($gestor->apply($soloRutas))
        ->toContain("@import '../../vendor/base/tenant/resources/css/base-tenant.css';")
        ->and(substr_count($gestor->apply($soloRutas), '/resources/views/**/*.blade.php'))->toBe(2);

    $soloImport = $appCss()."\n@import '../../vendor/base/tenant/resources/css/base-tenant.css';\n";

    expect($gestor->apply($soloImport))
        ->toContain("@source '../../vendor/base/tenant/resources/views/**/*.blade.php';")
        ->and(substr_count($gestor->apply($soloImport), 'base-tenant.css'))->toBe(1);
});

/**
 * `detach()` es la inversa: la ejecuta `k2labs-base:eject` cuando el paquete
 * deja de estar en `vendor/`. Las vistas copiadas siguen usando las escalas de
 * estado, así que el tema tiene que irse con ellas en lugar de desaparecer.
 */
test('reapunta el import a la copia local de la aplicación', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $resultado = $gestor->detach($gestor->apply($appCss()));

    expect($resultado)
        ->toContain("@import './base-tenant.css';")
        ->not->toContain('vendor/base/tenant/resources/css/base-tenant.css');
});

/**
 * Las vistas del paquete ya no existen: las copiadas viven en
 * `resources/views/tenant`, que cubre el `@source` propio de la aplicación.
 */
test('retira las rutas de escaneo que apuntaban al paquete', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $resultado = $gestor->detach($gestor->apply($appCss()));

    expect($resultado)
        // Las dos formas: enlazado es `base-tenant/`, en vendor es `base/tenant/`.
        ->not->toContain('base-tenant/resources/views')
        ->not->toContain('base/tenant/resources/views')
        // El `@source` de la propia aplicación se queda donde estaba.
        ->toContain("@source '../**/*.blade.php';");
});

test('separar dos veces no cambia nada', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $unaVez = $gestor->detach($gestor->apply($appCss()));

    expect($gestor->detach($unaVez))->toBe($unaVez);
});

test('un app.css que el instalador nunca tocó se queda igual', function () use ($appCss) {
    expect((new StylesheetManager)->detach($appCss()))->toBe($appCss());
});

/**
 * Instalaciones anteriores a la publicación del fichero de tema: el instalador
 * les puso las rutas de escaneo pero no el import. Sin esto, al separar se
 * quedarían sin las cuatro escalas de estado y los avisos perderían el color
 * en silencio, que es justo el fallo que el tema viene a evitar.
 */
test('añade el import local si la instalación solo tenía las rutas de escaneo', function () use ($appCss) {
    $antigua = $appCss()."\n@source '../../vendor/base/tenant/resources/views/**/*.blade.php';\n";

    $resultado = (new StylesheetManager)->detach($antigua);

    expect($resultado)
        ->toContain("@import './base-tenant.css';")
        ->not->toContain('base/tenant/resources/views');

    // Y sigue yendo detrás de Tailwind, por el mismo motivo que en `apply()`.
    expect(strpos($resultado, "@import 'tailwindcss';"))
        ->toBeLessThan(strpos($resultado, './base-tenant.css'));
});

/**
 * Formas documentadas de importar Tailwind v4 que el patrón original no veía.
 * Al no reconocerlas, el import del tema acababa por delante del de Tailwind,
 * que es exactamente lo que no debe pasar.
 */
test('reconoce las variantes del import de tailwind', function (string $tailwind) {
    $resultado = (new StylesheetManager)->apply($tailwind."\n\n@theme {\n}\n");

    expect(strpos($resultado, $tailwind))
        ->toBeLessThan(strpos($resultado, 'base-tenant.css'));
})->with([
    "@import 'tailwindcss';",
    '@import "tailwindcss";',
    "@import 'tailwindcss' source(none);",
    '@import "tailwindcss" theme(static);',
]);

/**
 * Sin import de Tailwind, el sitio correcto es detrás del último `@import`:
 * CSS exige que todos vayan al principio de la hoja, así que colarlo el primero
 * no rompe, pero dejarlo detrás de una regla sí.
 */
test('sin tailwind, el import va detrás del último import existente', function () {
    $css = "@import 'reset.css';\n@import 'fuentes.css';\n\n.algo { color: red; }\n";

    $resultado = (new StylesheetManager)->apply($css);

    expect(strpos($resultado, 'fuentes.css'))
        ->toBeLessThan(strpos($resultado, 'base-tenant.css'))
        ->and(strpos($resultado, 'base-tenant.css'))
        ->toBeLessThan(strpos($resultado, '.algo'));
});

/**
 * Reinstalar sobre una aplicación ya separada tiene que devolver el import a
 * `vendor/`. Con la comprobación laxa anterior, el import local contaba como
 * «ya está» y la aplicación se quedaba importando una copia local que podía no
 * existir.
 */
test('instalar sobre una aplicación separada devuelve el import a vendor', function () use ($appCss) {
    $gestor = new StylesheetManager;

    $instalado = $gestor->apply($appCss());
    $ciclo = $gestor->apply($gestor->detach($instalado));

    expect($ciclo)->toBe($instalado);
});

test('una mención suelta al fichero de tema no impide poner el import', function () {
    $css = "@import 'tailwindcss';\n\n/* pendiente: importar base-tenant.css */\n";

    $resultado = (new StylesheetManager)->apply($css);

    expect($resultado)->toContain("@import '../../vendor/base/tenant/resources/css/base-tenant.css';");
});

test('retira las rutas de escaneo entrecomilladas con dobles y sangradas', function () {
    $css = "@import 'tailwindcss';\n"
        ."@source \"../../vendor/base/tenant/resources/views/**/*.blade.php\";\n"
        ."    @source '../../base-tenant/resources/views/**/*.blade.php';\n";

    $resultado = (new StylesheetManager)->detach($css);

    expect($resultado)
        ->not->toContain('base-tenant/resources/views')
        ->not->toContain('base/tenant/resources/views');
});

/**
 * `base[\/-]tenant` sin anclar también casa dentro de `mybase/tenant`, que es
 * de otro paquete y no se toca.
 */
test('no toca rutas de otros paquetes con nombre parecido', function () {
    $css = "@import 'tailwindcss';\n@source '../../packages/mybase/tenant/resources/views/**/*.blade.php';\n";

    expect((new StylesheetManager)->detach($css))->toBe($css);
});

test('conserva los finales de línea del fichero', function () {
    $css = "@import 'tailwindcss';\r\n\r\n@theme {\r\n}\r\n";

    $resultado = (new StylesheetManager)->apply($css);

    // Ni un solo LF suelto: todos los saltos siguen siendo CRLF.
    expect(preg_match('/(?<!\r)\n/', $resultado))->toBe(0);
});

/**
 * El armazón de Flux coloca barra lateral, cabecera y contenido con
 * `grid-template-areas` desde su propia hoja de estilos. Sin ese import, los
 * tres se apilan en vertical y la barra lateral aparece encima del contenido,
 * además de quedarse sin estilo todos los componentes.
 */
test('importa la hoja de estilos de flux', function () use ($appCss) {
    $resultado = (new StylesheetManager)->apply($appCss());

    expect($resultado)->toContain("@import '../../vendor/livewire/flux/dist/flux.css';");
});

test('flux se importa después de tailwind y antes del tema del paquete', function () use ($appCss) {
    $resultado = (new StylesheetManager)->apply($appCss());

    expect(strpos($resultado, "@import 'tailwindcss';"))
        ->toBeLessThan(strpos($resultado, 'flux/dist/flux.css'))
        ->and(strpos($resultado, 'flux/dist/flux.css'))
        ->toBeLessThan(strpos($resultado, 'base-tenant.css'));
});

/**
 * Sin declarar la variante, `dark:` compila a `@media (prefers-color-scheme)` y
 * sigue al sistema operativo: el conmutador de Flux pone la clase `.dark` en el
 * `<html>` y nuestras utilidades no se enteran. Los componentes de Flux sí
 * cambian, porque su hoja usa `.dark` por dentro — de ahí que solo pareciera
 * cambiar «algunos botones».
 */
test('declara la variante dark por clase', function () use ($appCss) {
    $resultado = (new StylesheetManager)->apply($appCss());

    expect($resultado)->toContain('@custom-variant dark (&:where(.dark, .dark *));');
});
