# Fundamentos del tema — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el paquete pinte con una paleta que existe de verdad, y que tres tests impidan que vuelva a dejar de existir.

**Architecture:** El paquete publica un único fichero de tema con las cuatro escalas de estado que Flux no cubre (`success`, `warning`, `danger`, `info`); todo lo demás sale de Flux (`zinc` para superficies y texto, `accent` para la acción principal). Las vistas se barren de una vez para hablar solo ese vocabulario. El instalador pasa a importar el fichero en lugar de inyectar un bloque de alias en el `app.css` de la aplicación.

**Tech Stack:** Laravel 12, Tailwind CSS v4 (`@theme`, `@source`), Flux UI v2.15 (gratuito), Pest 3, Livewire 4.

**Alcance:** Este es el plan 1 de 4 del rediseño descrito en `docs/superpowers/specs/2026-08-02-rediseno-ux-ui-design.md`. Cubre la sección 1 del spec (fundamentos) y las tres guardas de la sección 5. El shell (sección 4), el patrón de tabla (sección 2) y el de formulario (sección 3) van en planes posteriores, porque cada uno reestructura markup y este plan solo cambia vocabulario.

**Nota de secuencia:** Este plan **no** borra los 17 componentes anónimos que sustituye Flux (`input-label`, `text-input`, `primary-button`…). Tienen 216 usos vivos en vistas que reestructuran los planes 2 y 3; borrarlos ahora rompería la aplicación y el `ViewCompilationTest`. Aquí solo se borran los 19 ficheros que hoy no usa nadie.

---

### Task 1: Fichero de tema con las escalas de estado

**Files:**
- Create: `resources/css/base-tenant.css`
- Create: `tests/Feature/DesignTokensTest.php`

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/DesignTokensTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Las escalas que el paquete aporta. Flux ya define zinc y accent; estas
 * cuatro son las que no cubre y las que usan badges, avisos y banners.
 */
const ESTADOS = ['success', 'warning', 'danger', 'info'];

const TONOS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];

test('el tema define las cuatro escalas de estado enteras', function () {
    $tema = File::get(dirname(__DIR__, 2).'/resources/css/base-tenant.css');

    $faltan = [];

    foreach (ESTADOS as $estado) {
        foreach (TONOS as $tono) {
            if (! str_contains($tema, "--color-{$estado}-{$tono}:")) {
                $faltan[] = "--color-{$estado}-{$tono}";
            }
        }

        // Las vistas también usan la forma plana (`bg-success`), que hoy
        // inyecta el instalador y pasa a vivir aquí.
        if (! str_contains($tema, "--color-{$estado}:")) {
            $faltan[] = "--color-{$estado}";
        }
    }

    expect($faltan)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/DesignTokensTest.php`
Expected: FAIL — `File does not exist at path .../resources/css/base-tenant.css`

- [ ] **Step 3: Write the theme file**

Crea `resources/css/base-tenant.css`:

```css
/**
 * Escalas de estado del paquete.
 *
 * Flux define `zinc` (superficies y texto) y `accent` (acción principal). Estas
 * cuatro escalas son las que no cubre, y son las que usan los badges de estado,
 * los avisos y el banner de suplantación.
 *
 * La aplicación lo importa desde su propio `app.css`, después de Tailwind:
 *
 *     @import 'tailwindcss';
 *     @import '../../vendor/base/tenant/resources/css/base-tenant.css';
 *
 * Para cambiar la paleta, redefine cualquiera de estos tokens en el `@theme`
 * de la aplicación: gana el último que se declara.
 */

@theme {
    --color-success-50: var(--color-green-50);
    --color-success-100: var(--color-green-100);
    --color-success-200: var(--color-green-200);
    --color-success-300: var(--color-green-300);
    --color-success-400: var(--color-green-400);
    --color-success-500: var(--color-green-500);
    --color-success-600: var(--color-green-600);
    --color-success-700: var(--color-green-700);
    --color-success-800: var(--color-green-800);
    --color-success-900: var(--color-green-900);
    --color-success: var(--color-green-500);

    --color-warning-50: var(--color-amber-50);
    --color-warning-100: var(--color-amber-100);
    --color-warning-200: var(--color-amber-200);
    --color-warning-300: var(--color-amber-300);
    --color-warning-400: var(--color-amber-400);
    --color-warning-500: var(--color-amber-500);
    --color-warning-600: var(--color-amber-600);
    --color-warning-700: var(--color-amber-700);
    --color-warning-800: var(--color-amber-800);
    --color-warning-900: var(--color-amber-900);
    --color-warning: var(--color-amber-500);

    --color-danger-50: var(--color-red-50);
    --color-danger-100: var(--color-red-100);
    --color-danger-200: var(--color-red-200);
    --color-danger-300: var(--color-red-300);
    --color-danger-400: var(--color-red-400);
    --color-danger-500: var(--color-red-500);
    --color-danger-600: var(--color-red-600);
    --color-danger-700: var(--color-red-700);
    --color-danger-800: var(--color-red-800);
    --color-danger-900: var(--color-red-900);
    --color-danger: var(--color-red-500);

    --color-info-50: var(--color-blue-50);
    --color-info-100: var(--color-blue-100);
    --color-info-200: var(--color-blue-200);
    --color-info-300: var(--color-blue-300);
    --color-info-400: var(--color-blue-400);
    --color-info-500: var(--color-blue-500);
    --color-info-600: var(--color-blue-600);
    --color-info-700: var(--color-blue-700);
    --color-info-800: var(--color-blue-800);
    --color-info-900: var(--color-blue-900);
    --color-info: var(--color-blue-500);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/DesignTokensTest.php`
Expected: PASS — 1 passed

- [ ] **Step 5: Commit**

```bash
git add resources/css/base-tenant.css tests/Feature/DesignTokensTest.php
git commit -m "feat: ship the state colour scales the views depend on"
```

---

### Task 2: Barrido del vocabulario de color

**Files:**
- Modify: `tests/Feature/DesignTokensTest.php` (añadir el segundo test)
- Modify: todas las vistas bajo `resources/views/` (barrido mecánico)

- [ ] **Step 1: Write the failing test**

Añade al final de `tests/Feature/DesignTokensTest.php`:

```php
/**
 * El fallo que motivó el rediseño: en Tailwind v4 una clase de una escala que
 * no existe no da error, simplemente no aplica la propiedad. `border` y
 * `divide-y` sí aplican grosor, así que heredan currentColor y los separadores
 * salen casi negros.
 */
test('las vistas solo usan escalas de color que el tema define', function () {
    $vistas = dirname(__DIR__, 2).'/resources/views';

    // zinc y accent los garantiza Flux; el resto sale del fichero de tema.
    $permitidas = ['zinc', 'accent', ...ESTADOS];

    $infractoras = [];

    foreach (File::allFiles($vistas) as $fichero) {
        if (! str_ends_with($fichero->getFilename(), '.blade.php')) {
            continue;
        }

        preg_match_all(
            '/\b(?:bg|text|border|ring|divide|from|via|to|placeholder|shadow)-([a-z]+)-\d{2,3}\b/',
            File::get($fichero->getPathname()),
            $coincidencias
        );

        foreach (array_unique($coincidencias[1]) as $escala) {
            if (! in_array($escala, $permitidas, true)) {
                $infractoras[] = $fichero->getRelativePathname().': '.$escala;
            }
        }
    }

    expect(array_unique($infractoras))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/DesignTokensTest.php`
Expected: FAIL — la lista incluye `primary`, `secondary`, `surface`, `gray`, `indigo`, `red`, `green`, `yellow`, `amber`, `blue`, `sky`, `error`

- [ ] **Step 3: Run the sweep**

Las equivalencias no son inventadas: `primary` ya era un alias de `zinc` y `surface` de `gray` en el bloque que inyecta el instalador, así que ambas colapsan en `zinc` sin cambiar un solo píxel. `indigo` era la acción principal en las vistas de autenticación, y pasa a `accent`.

```bash
find resources/views -name '*.blade.php' -print0 | xargs -0 perl -pi -e '
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-(primary|secondary|surface|gray)-(\d{2,3})\b/$1-zinc-$3/g;
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-indigo-(\d{2,3})\b/$1-accent-$2/g;
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-(red|error)-(\d{2,3})\b/$1-danger-$3/g;
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-green-(\d{2,3})\b/$1-success-$2/g;
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-(yellow|amber)-(\d{2,3})\b/$1-warning-$3/g;
  s/\b(bg|text|border|ring|divide|from|via|to|placeholder)-(blue|sky)-(\d{2,3})\b/$1-info-$3/g;
  s/\b(bg|text|border|ring|divide)-error(?![-\w])/$1-danger/g;
  s/\bshadow-soft\b/shadow-sm/g;
'
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/DesignTokensTest.php`
Expected: PASS — 2 passed

Comprueba además que no queda ninguna de las escalas retiradas ni la sombra inexistente:

Run: `grep -rcE "\-(primary|secondary|surface|gray|indigo|red|green|yellow|amber|blue|sky|error)-[0-9]|shadow-soft" resources/views --include='*.blade.php' | grep -v ':0' | head`
Expected: sin salida

- [ ] **Step 5: Verify nothing else broke**

Run: `./vendor/bin/pest`
Expected: PASS — 141 passed (139 antes de este plan, más los 2 de `DesignTokensTest`)

Si `ViewCompilationTest` falla, el barrido ha tocado algo que no debía: revisa el diff de la vista que señale.

- [ ] **Step 6: Commit**

```bash
git add resources/views tests/Feature/DesignTokensTest.php
git commit -m "refactor: speak a single colour vocabulary across the views"
```

---

### Task 3: El instalador importa el tema

Hoy `InstallCommand::updateAppCss()` inyecta a mano un bloque de alias dentro del `@theme` de la aplicación. Con el fichero de tema publicado, basta con importarlo. La lógica se extrae a un objeto de soporte, como el resto de la mecánica del instalador (`EnvironmentManager`, `MigrationRunner`, `UserModelManager`), porque un método protegido de un comando no se puede probar.

**Files:**
- Create: `src/Console/Support/StylesheetManager.php`
- Create: `tests/Feature/StylesheetManagerTest.php`
- Modify: `src/Console/Commands/InstallCommand.php:1054-1142`

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/StylesheetManagerTest.php`:

```php
<?php

declare(strict_types=1);

use Base\Tenant\Console\Support\StylesheetManager;

const CSS_LARAVEL = <<<'CSS'
@import 'tailwindcss';

@source '../**/*.blade.php';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui;
}
CSS;

test('añade el import del tema y las rutas de escaneo', function () {
    $resultado = (new StylesheetManager)->apply(CSS_LARAVEL);

    expect($resultado)
        ->toContain("@import '../../vendor/base/tenant/resources/css/base-tenant.css';")
        ->toContain("@source '../../vendor/base/tenant/resources/views/**/*.blade.php';")
        // La ruta de desarrollo, para quien tiene el paquete enlazado.
        ->toContain("@source '../../base-tenant/resources/views/**/*.blade.php';");
});

test('el import va después de tailwind, o las utilidades no se generan', function () {
    $resultado = (new StylesheetManager)->apply(CSS_LARAVEL);

    expect(strpos($resultado, "@import 'tailwindcss';"))
        ->toBeLessThan(strpos($resultado, 'base-tenant.css'));
});

test('aplicarlo dos veces no duplica nada', function () {
    $manager = new StylesheetManager;

    $unaVez = $manager->apply(CSS_LARAVEL);
    $dosVeces = $manager->apply($unaVez);

    expect($dosVeces)->toBe($unaVez)
        ->and(substr_count($dosVeces, 'base-tenant.css'))->toBe(1);
});

test('respeta un app.css que no tiene bloque @theme', function () {
    $resultado = (new StylesheetManager)->apply("@import 'tailwindcss';\n");

    expect($resultado)->toContain('base-tenant.css');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/StylesheetManagerTest.php`
Expected: FAIL — `Class "Base\Tenant\Console\Support\StylesheetManager" not found`

- [ ] **Step 3: Write the implementation**

Crea `src/Console/Support/StylesheetManager.php`:

```php
<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

/**
 * Conecta el `app.css` de la aplicación con el paquete: importa el tema y
 * declara las rutas que Tailwind tiene que escanear para no descartar las
 * clases que solo aparecen en las vistas del paquete.
 *
 * Idempotente: el instalador puede volver a pasar sobre un fichero ya tocado.
 */
class StylesheetManager
{
    protected const IMPORT = "@import '../../vendor/base/tenant/resources/css/base-tenant.css';";

    /** Enlazado en desarrollo y en vendor en producción: se declaran los dos. */
    protected const SOURCES = [
        "@source '../../base-tenant/resources/views/**/*.blade.php';",
        "@source '../../vendor/base/tenant/resources/views/**/*.blade.php';",
    ];

    public function apply(string $contents): string
    {
        $contents = $this->addImport($contents);

        return $this->addSources($contents);
    }

    /**
     * Va justo detrás del import de Tailwind: antes, las utilidades del tema no
     * llegarían a generarse.
     */
    protected function addImport(string $contents): string
    {
        if (str_contains($contents, 'base-tenant.css')) {
            return $contents;
        }

        if (preg_match("/^@import\s+'tailwindcss';\s*$/m", $contents, $match)) {
            return str_replace($match[0], $match[0]."\n".self::IMPORT, $contents);
        }

        return self::IMPORT."\n".$contents;
    }

    protected function addSources(string $contents): string
    {
        foreach (self::SOURCES as $source) {
            if (! str_contains($contents, $source)) {
                $contents = rtrim($contents)."\n".$source."\n";
            }
        }

        return $contents;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/StylesheetManagerTest.php`
Expected: PASS — 4 passed

- [ ] **Step 5: Point the installer at it**

En `src/Console/Commands/InstallCommand.php`, sustituye el método `updateAppCss()` entero (líneas 1054-1142, desde `protected function updateAppCss(): void` hasta su llave de cierre) por:

```php
    /**
     * Conecta el app.css de la aplicación con el tema y las vistas del paquete.
     */
    protected function updateAppCss(): void
    {
        $cssPath = resource_path('css/app.css');

        if (! File::exists($cssPath)) {
            return;
        }

        $original = File::get($cssPath);
        $updated = (new StylesheetManager)->apply($original);

        if ($updated === $original) {
            return;
        }

        File::put($cssPath, $updated);

        $this->components->info('Updated app.css with base-tenant configuration');
    }
```

Y añade el import, junto a los otros de `Console\Support`:

```php
use Base\Tenant\Console\Support\StylesheetManager;
```

- [ ] **Step 6: Run the suite**

Run: `./vendor/bin/pest`
Expected: PASS — todo verde

- [ ] **Step 7: Commit**

```bash
git add src/Console/Support/StylesheetManager.php src/Console/Commands/InstallCommand.php tests/Feature/StylesheetManagerTest.php
git commit -m "refactor: import the package theme instead of injecting colour aliases"
```

---

### Task 4: Borrar lo que no usa nadie

Diecinueve ficheros: once vistas y ocho componentes anónimos. Ninguno está referenciado por código, vistas ni configuración. Seis de ellos son un duplicado completo de las pantallas de autenticación (`livewire/pages/auth/`), que confunde a quien busca dónde se edita el login.

**Files:**
- Create: `tests/Feature/OrphanViewsTest.php`
- Delete: los 19 ficheros del paso 3

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/OrphanViewsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Una vista que no referencia nadie no se borra sola: se queda, alguien la
 * edita por error, y el duplicado de `livewire/pages/auth/` demuestra hasta
 * dónde llega la confusión.
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

    $huerfanas = [];

    foreach (File::allFiles("{$raiz}/resources/views") as $fichero) {
        if (! str_ends_with($fichero->getFilename(), '.blade.php')) {
            continue;
        }

        $relativa = $fichero->getRelativePathname();
        $nombre = str_replace(['/', '.blade.php'], ['.', ''], $relativa);

        // Cómo puede referenciarse: por nombre de vista, como etiqueta de
        // componente, o por el nombre corto que registra Livewire.
        $agujas = [$nombre];

        if (str_starts_with($nombre, 'components.')) {
            $componente = substr($nombre, strlen('components.'));
            $agujas[] = 'x-base-tenant::'.$componente;
            $agujas[] = "'".$componente."'";
        }

        if (str_starts_with($nombre, 'livewire.')) {
            $agujas[] = substr($nombre, strlen('livewire.'));
        }

        $referenciada = false;

        foreach ($agujas as $aguja) {
            if (str_contains($pajar, $aguja)) {
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/OrphanViewsTest.php`
Expected: FAIL — lista de 16 ficheros, empezando por `components/confirmation-modal.blade.php`

- [ ] **Step 3: Delete the dead files**

```bash
git rm resources/views/livewire/pages/auth/confirm-password.blade.php \
       resources/views/livewire/pages/auth/forgot-password.blade.php \
       resources/views/livewire/pages/auth/login.blade.php \
       resources/views/livewire/pages/auth/register.blade.php \
       resources/views/livewire/pages/auth/reset-password.blade.php \
       resources/views/livewire/pages/auth/verify-email.blade.php \
       resources/views/livewire/layout/navigation.blade.php \
       resources/views/livewire/welcome/navigation.blade.php \
       resources/views/layouts/navigation-mobile.blade.php \
       resources/views/layouts/header.blade.php \
       resources/views/layouts/language-selector.blade.php \
       resources/views/components/confirmation-modal.blade.php \
       resources/views/components/dialog-modal.blade.php \
       resources/views/components/locale-switcher.blade.php \
       resources/views/components/mini-chart.blade.php \
       resources/views/components/ui-kit.blade.php \
       resources/views/components/button.blade.php \
       resources/views/components/label.blade.php \
       resources/views/components/input.blade.php
```

Los tres últimos (`button`, `label`, `input`) no salen en el test porque su nombre corto aparece en otros contextos, pero tienen cero usos como componente. Compruébalo antes de borrarlos:

Run: `grep -rc "x-base-tenant::button\b\|x-base-tenant::label\b\|x-base-tenant::input\b" resources/views --include='*.blade.php' | grep -v ':0'`
Expected: sin salida

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/OrphanViewsTest.php`
Expected: PASS — 1 passed

- [ ] **Step 5: Verify the application still compiles**

Run: `./vendor/bin/pest`
Expected: PASS — todo verde, `ViewCompilationTest` incluido

- [ ] **Step 6: Commit**

```bash
git add -A resources/views tests/Feature/OrphanViewsTest.php
git commit -m "chore: remove the views and components nothing references"
```

---

### Task 5: Guarda de traducciones

La tercera forma en que esto se pudre: una clave que se usa en una vista y no existe en el fichero de idioma sale por pantalla tal cual. Así estuvo el log de actividad entero, con `activity.php` sin crear en ninguno de los dos idiomas.

**Files:**
- Create: `tests/Feature/TranslationKeysTest.php`

- [ ] **Step 1: Write the test**

Crea `tests/Feature/TranslationKeysTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Solo claves literales: las que se construyen concatenando
 * (`__('base-tenant::permissions.names.'.$permission->name)`) dependen de datos
 * y no se pueden resolver leyendo el código.
 */
function clavesUsadas(): array
{
    $raiz = dirname(__DIR__, 2);
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
}

test('toda clave de traducción existe en inglés y en español', function (string $idioma) {
    $raiz = dirname(__DIR__, 2);
    $faltan = [];

    foreach (clavesUsadas() as $clave) {
        [$fichero, $resto] = explode('.', $clave, 2);

        // `layouts.` y `livewire.` son nombres de vista, no de traducción.
        if (in_array($fichero, ['layouts', 'livewire', 'components'], true)) {
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
```

- [ ] **Step 2: Run the test**

Run: `./vendor/bin/pest tests/Feature/TranslationKeysTest.php`
Expected: PASS — 2 passed (los ficheros que faltaban se crearon el 2026-08-01)

Si falla, la salida nombra la clave y el idioma: añádela al fichero correspondiente en `resources/lang/en/` y `resources/lang/es/` antes de seguir.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/TranslationKeysTest.php
git commit -m "test: fail when a translation key is missing in either language"
```

---

### Task 6: Retirar las reliquias de Tailwind v3

Dos ficheros que no participan en la compilación real —que va por `@source` desde la aplicación— y que hoy hacen perder el tiempo a quien busca dónde se configura el tema. `docs/FRONTEND.md` documenta un flujo con Laravel Mix, presets de `tailwind.config.js` y `npm run build` dentro de `vendor/` que ya no existe.

**Files:**
- Delete: `tailwind.config.js`, `resources/css/app.css`
- Modify: `docs/FRONTEND.md`

- [ ] **Step 1: Confirm what references them**

Run: `grep -rn "tailwind.config\|css/app.css" src/ tests/ docs/ package.json vite.config.js 2>/dev/null | grep -v "resources/css/base-tenant"`
Expected: `docs/FRONTEND.md` y la lista de entradas de `vite.config.js`

`vite.config.js` declara `resources/css/app.css` como entrada, así que borrarlo sin tocar la configuración deja el build roto. Se quitan las dos entradas: la otra, `resources/js/app.js`, apunta a un fichero que no existe en el repositorio, de modo que ese build ya está roto hoy. Ver la cuestión abierta al final del plan.

- [ ] **Step 2: Delete them and drop the stale build entries**

```bash
git rm tailwind.config.js resources/css/app.css
```

Y en `vite.config.js`, sustituye el bloque del plugin por uno que declare la única hoja que el paquete aporta:

```js
        laravel({
            input: [
                'resources/css/base-tenant.css',
            ],
            refresh: true,
        }),
```

- [ ] **Step 3: Rewrite the frontend documentation**

Sustituye el contenido completo de `docs/FRONTEND.md` por:

```markdown
# Frontend

## Cómo se compilan los estilos

El paquete no compila nada por su cuenta: los estilos los construye la
aplicación con Tailwind v4, y el paquete solo aporta dos cosas al `app.css`.

El instalador las añade automáticamente:

```css
@import 'tailwindcss';
@import '../../vendor/base/tenant/resources/css/base-tenant.css';

@source '../../base-tenant/resources/views/**/*.blade.php';
@source '../../vendor/base/tenant/resources/views/**/*.blade.php';
```

El `@import` trae las escalas de estado (`success`, `warning`, `danger`,
`info`). Los `@source` le dicen a Tailwind que mire dentro de las vistas del
paquete, o descartaría todas las clases que solo aparecen ahí. Se declaran las
dos rutas porque el paquete puede estar enlazado en desarrollo o instalado en
`vendor/`.

## Vocabulario de color

| Uso | Escala |
|---|---|
| Superficies y texto | `zinc` |
| Acción principal | `accent` |
| Estados | `success`, `warning`, `danger`, `info` |

Las tres primeras las define Flux; las de estado, el paquete. No se usan
escalas crudas de Tailwind (`gray`, `red`, `green`…) en las vistas: hay un test
que falla si aparece una.

## Cambiar la paleta

Redefine los tokens en el `@theme` de tu aplicación, después del import. Gana
el último que se declara:

```css
@theme {
    --color-accent-500: var(--color-emerald-500);
    --color-accent-600: var(--color-emerald-600);
}
```

## Componentes

Los formularios, tablas, modales y navegación son componentes de
[Flux UI](https://fluxui.dev) en su versión gratuita. El paquete solo mantiene
dos componentes propios: `application-logo` y `menu-tree`.
```

- [ ] **Step 4: Retire the same instructions from the installation guide**

`docs/INSTALLATION.md` tiene una sección **5.3 «Define Flux Color Tokens»** (líneas 239-288) que pide al usuario copiar a mano el bloque de alias: las tres escalas viejas más los cuatro tokens planos, con `--color-error` en vez de `--color-danger`. Es la misma duplicación que retira la tarea 3, pero en la documentación, y sobrevive a todo el rediseño si no se toca aquí.

Sustituye esa sección entera —desde el encabezado `### 5.3: Define Flux Color Tokens` hasta la línea que empieza `**Note:** You can customize these mappings`, ambas incluidas— por:

```markdown
### 5.3: Colour tokens

Nothing to do by hand. The installer adds this to your `resources/css/app.css`:

```css
@import 'tailwindcss';
@import '../../vendor/base/tenant/resources/css/base-tenant.css';
```

That import brings the state scales the package needs (`success`, `warning`,
`danger`, `info`). Surfaces, text and the primary action come from Flux's own
`zinc` and `accent`, so there is nothing else to declare.

To use your own brand colours, redefine the tokens in your application's
`@theme` **after** the import — Tailwind merges `@theme` blocks in source
order, so the last declaration wins:

```css
@theme {
    --color-accent-500: var(--color-emerald-500);
    --color-accent-600: var(--color-emerald-600);
}
```

See `docs/FRONTEND.md` for the full colour vocabulary.
```

- [ ] **Step 5: Run the suite**

Run: `./vendor/bin/pest`
Expected: PASS — todo verde

- [ ] **Step 6: Commit**

```bash
git add -A tailwind.config.js resources/css/app.css vite.config.js docs/FRONTEND.md docs/INSTALLATION.md
git commit -m "docs: describe the Tailwind v4 setup the package actually uses"
```

---

## Cuestión abierta: la cadena de build del paquete

Al preparar la tarea 6 salió algo que el spec no contempla y que **no está decidido**, así que no hay tarea para ello:

- `vite.config.js` declara como entrada `resources/js/app.js`, **que no existe en el repositorio**. `npm run build` falla hoy.
- `package.json` fija `tailwindcss ^3.4.0`, dos versiones mayores por detrás de la que usan las aplicaciones, y no lo consume nadie.
- El proveedor publica `__DIR__.'/../public'` bajo la etiqueta `base-tenant-assets`, y **ese directorio tampoco existe**. El instalador ejecuta ese `vendor:publish` en cada instalación y no copia nada.
- `docs/FRONTEND.md` describía compilar assets dentro de `vendor/` y publicarlos, un flujo que no funciona desde hace tiempo.

La lectura razonable es que el paquete no debería compilar nada: la aplicación anfitriona compila, y por eso existen los `@source`. Eso implicaría borrar `vite.config.js`, `package.json` y `package-lock.json`, y retirar la publicación de `base-tenant-assets` del proveedor y del instalador.

Es una decisión de producto, no de diseño visual. Hasta tomarla, la tarea 6 se limita a dejar el build coherente con los ficheros que sí existen.

---

## Definición de terminado

- [ ] `./vendor/bin/pest` en verde, con los cuatro tests nuevos (`DesignTokensTest`, `StylesheetManagerTest`, `OrphanViewsTest`, `TranslationKeysTest`).
- [ ] `grep -rE "\-(primary|secondary|surface|gray)-[0-9]" resources/views` sin resultados.
- [ ] `./vendor/bin/pint --dirty` sin cambios pendientes.
- [ ] Instalación limpia sobre una aplicación nueva: el `app.css` resultante contiene el `@import` del tema y los dos `@source`.

## Qué queda para los siguientes planes

| Plan | Contenido | Sección del spec |
|---|---|---|
| 2 | Shell con `flux:sidebar`, `flux:navlist`, `flux:header` y `flux:main`; selector de tema; parejas de claro/oscuro | 4 |
| 3 | Patrón de tabla en usuarios, cuentas, invitaciones, actividad y features; trait `InteractsWithTable` | 2 |
| 4 | Patrón de formulario a dos columnas; borrado de los 17 componentes que sustituye Flux; confirmaciones con `flux:modal` | 3 y 5 |
