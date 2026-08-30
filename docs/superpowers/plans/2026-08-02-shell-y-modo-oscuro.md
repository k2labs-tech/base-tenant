# Shell y modo oscuro — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el armazón de la aplicación —barra lateral, cabecera, menú de usuario y pantalla de invitado— se construya con componentes de Flux en lugar de Alpine escrito a mano, y que funcione en claro y en oscuro con un selector.

**Architecture:** El layout pasa a `flux:sidebar` + `flux:navlist` + `flux:header` + `flux:main`, todos de la versión gratuita de Flux, que resuelven plegado, cajón móvil, accesibilidad de teclado y modo oscuro sin código propio. La fuente de los menús no cambia: sigue siendo `MenuManager`, con sus permisos, sus features y sus resolvedores de badge; la vista solo traduce cada entrada a un `flux:navlist.item`. El selector de tema usa el mecanismo de Flux (`$flux.appearance`, respaldado por `localStorage`), sin tocar el backend.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI v2.15 (gratuito), Tailwind v4, Alpine (solo el que trae Flux), Pest 3.

**Alcance:** Plan 2 de 4 del rediseño descrito en `docs/superpowers/specs/2026-08-02-rediseno-ux-ui-design.md`. Cubre la sección 4 del spec (shell) y la parte de la sección 1 que trata el modo oscuro. El plan 1 (fundamentos del tema) está terminado: `resources/css/base-tenant.css` define `accent` y las cuatro escalas de estado, las vistas hablan un solo vocabulario y hay cuatro guardas en la suite.

**Nota de alcance — el modo oscuro se aplica a las 55 vistas, por decisión explícita.** La versión anterior de este plan lo limitaba al armazón, con el argumento de que las tablas y formularios se reescriben enteros en los planes 3 y 4 y las parejas `dark:` escritas ahora se tirarían. El propietario del producto ha decidido cubrirlas todas ahora, de modo que la aplicación quede utilizable en oscuro de punta a punta sin esperar a los dos planes siguientes. El coste asumido es que parte del trabajo de la tarea 6 se rehará; a cambio, no queda ninguna pantalla que se rompa visualmente al cambiar de tema.

---

### Task 1: Cerrar la limpieza de huérfanos y arreglar el punto ciego de la guarda

El plan 1 borró 23 ficheros, pero su test decide "referenciado" buscando el nombre corto como subcadena, y eso deja pasar cualquier nombre que sea palabra común. Ya se sabe de tres casos: `dropdown` (que el test da por vivo porque la cadena aparece en `dropdown-link`), y `layouts/navigation.blade.php`, que sobrevive porque la aguja `layouts.navigation` casa con el `layouts.navigation-items` de otro fichero. Ese `navigation.blade.php` además incluye `layouts.navigation-items` y `layouts.navigation-master-data` **sin el espacio de nombres del paquete**, así que ni siquiera resolvería si alguien lo renderizara.

**Files:**
- Modify: `tests/Feature/OrphanViewsTest.php`
- Delete: `resources/views/layouts/navigation.blade.php`, `resources/views/layouts/navigation-master-data.blade.php`, `resources/views/components/dropdown.blade.php`

- [ ] **Step 1: Make the guard match whole references instead of substrings**

En `tests/Feature/OrphanViewsTest.php`, sustituye la construcción de agujas y la comprobación por una que exija que la referencia termine donde termina el nombre. Un componente se referencia como `x-base-tenant::nombre` seguido de algo que no sea letra ni guion; una vista, como `'nombre'` o `"nombre"` o `::nombre` con el mismo corte.

```php
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
        }

        $referenciada = false;

        foreach ($patrones as $patron) {
            if (preg_match($patron, $pajar) === 1) {
                $referenciada = true;
                break;
            }
        }
```

Actualiza el docblock para que diga por qué el corte importa: la versión anterior daba por vivo cualquier componente cuyo nombre fuera prefijo de otro.

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/OrphanViewsTest.php`
Expected: FAIL, nombrando al menos `components/dropdown.blade.php`, `layouts/navigation.blade.php` y `layouts/navigation-master-data.blade.php`.

Si aparecen más de esos tres, **párate y repórtalo antes de borrar nada**. Un patrón más estricto puede convertir en huérfano algo que sí se usa por una vía que no he previsto —una vista resuelta dinámicamente, un componente referenciado desde `config`—, y prefiero revisar la lista a que borres por obediencia.

- [ ] **Step 3: Delete the three files**

```bash
git rm resources/views/layouts/navigation.blade.php \
       resources/views/layouts/navigation-master-data.blade.php \
       resources/views/components/dropdown.blade.php
```

- [ ] **Step 4: Verify**

Run: `./vendor/bin/pest`
Expected: PASS. `ViewCompilationTest` demuestra que ninguna vista superviviente referencia lo borrado.

- [ ] **Step 5: Commit**

```bash
git add -A resources/views tests/Feature/OrphanViewsTest.php
git commit -m "chore: match whole references in the orphan guard and drop what it was hiding"
```

---

### Task 2: La navegación sale por `flux:navlist`

`navigation-items.blade.php` monta hoy sus propias secciones plegables con Alpine y `localStorage`, y delega en el componente `menu-tree`, que dibuja a mano enlaces, iconos, badges y submenús recursivos. `flux:navlist` hace exactamente eso: `flux:navlist.group` acepta `expandable` y `heading`, y `flux:navlist.item` acepta `icon`, `badge`, `badgeColor` y `current`.

**Lo que no cambia:** de dónde salen los datos. `Menu::tree($clave)` sigue devolviendo la colección de entradas ya filtradas por permiso y por feature, cada una con `title`, `href`, `icon`, `badge`, `active`, `target` y `children`.

**Files:**
- Rewrite: `resources/views/layouts/navigation-items.blade.php`
- Delete: `resources/views/components/menu-tree.blade.php`, `resources/views/components/icon.blade.php`
- Test: `tests/Feature/NavigationRenderTest.php` (nuevo)

Sobre los dos borrados: `menu-tree` solo lo usa `navigation-items`, y `icon` solo lo usa `menu-tree`. El spec decía que `menu-tree` sobrevivía porque Flux no cubre "un árbol ordenable" — eso es cierto del editor de menús (`navigation-manager.blade.php`), que tiene su propio markup y no usa este componente. Verifícalo antes de borrar: `grep -rn "menu-tree\|base-tenant::icon" resources/views src`.

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/NavigationRenderTest.php`:

```php
<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;

/**
 * La navegación es lo único del armazón que depende de datos: si el mapeo de
 * `Menu::tree()` a `flux:navlist` se rompe, el usuario se queda sin menú y
 * ninguna otra prueba se entera.
 */
beforeEach(function () {
    $this->syncPermissions();
    Menu::sync();
});

test('la barra lateral lista las entradas que el usuario puede ver', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $this->get(route('base-tenant.dashboard'))
        ->assertOk()
        ->assertSee('data-flux-navlist', false)
        ->assertSee(__('base-tenant::app.navigation.users'))
        ->assertSee(route('base-tenant.users.index'));
});

test('no lista lo que el permiso no alcanza', function () {
    $cuenta = $this->createAccount();
    $viewer = $this->createUser($cuenta, 'customer-viewer');

    $this->actingAsTenant($viewer, $cuenta);

    $this->get(route('base-tenant.dashboard'))
        ->assertOk()
        ->assertDontSee(route('base-tenant.users.index'));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/NavigationRenderTest.php`
Expected: FAIL en la primera prueba — la salida actual no contiene `data-flux-navlist`, porque la navegación todavía no usa Flux. La segunda debería pasar ya (el filtrado por permiso es de `MenuManager`, no de la vista); si falla, párate: significa que el menú está mostrando cosas que no debería y eso es un fallo de seguridad, no de diseño.

- [ ] **Step 3: Rewrite the navigation partial**

Sustituye el contenido completo de `resources/views/layouts/navigation-items.blade.php` por:

```blade
@php
    use Base\Tenant\Facades\Menu;

    $secciones = collect(config('base-tenant.menu.default_menus', ['main', 'settings']))
        ->map(fn (string $clave): array => ['clave' => $clave, 'items' => Menu::tree($clave)])
        ->filter(fn (array $seccion): bool => $seccion['items']->isNotEmpty());
@endphp

<flux:navlist variant="outline">
    @foreach($secciones as $seccion)
        <flux:navlist.group expandable :heading="__('base-tenant::menus.names.'.$seccion['clave'])">
            @foreach($seccion['items'] as $item)
                @include('base-tenant::layouts.navigation-item', ['item' => $item])
            @endforeach
        </flux:navlist.group>
    @endforeach
</flux:navlist>
```

Y crea `resources/views/layouts/navigation-item.blade.php`, que se incluye a sí mismo para los hijos:

```blade
@if(empty($item['children']))
    <flux:navlist.item
        :href="$item['href'] ?? '#'"
        :icon="$item['icon']"
        :current="$item['active']"
        :badge="$item['badge']"
        @if($item['target']) target="{{ $item['target'] }}" @endif
        wire:navigate
    >{{ $item['title'] }}</flux:navlist.item>
@else
    <flux:navlist.group expandable :heading="$item['title']" :expanded="$item['active']">
        @foreach($item['children'] as $hijo)
            @include('base-tenant::layouts.navigation-item', ['item' => $hijo])
        @endforeach
    </flux:navlist.group>
@endif
```

**Verifica cada prop contra el componente real antes de darlo por bueno.** Lee `vendor/livewire/flux/stubs/resources/views/flux/navlist/item.blade.php` y `.../navlist/group.blade.php` y comprueba que `current`, `badge`, `icon`, `heading`, `expandable` y `expanded` existen y se llaman así. El markup de arriba es mi lectura de esos componentes, no una cita: si algún prop no existe o se llama de otra forma, corrígelo y dilo en el informe. Un plan anterior dictó markup equivocado y costó tres rondas.

- [ ] **Step 4: Delete the components it replaces**

```bash
git rm resources/views/components/menu-tree.blade.php resources/views/components/icon.blade.php
```

- [ ] **Step 5: Verify**

Run: `./vendor/bin/pest tests/Feature/NavigationRenderTest.php` → 2 passed.
Run: `./vendor/bin/pest` → toda verde, incluida `OrphanViewsTest`.

- [ ] **Step 6: Commit**

```bash
git add -A resources/views tests/Feature/NavigationRenderTest.php
git commit -m "refactor: render the navigation with flux navlist"
```

---

### Task 3: El layout de aplicación, el menú de usuario y el selector de tema

`layouts/app.blade.php` son unas 200 líneas: barra lateral con plegado propio y `localStorage`, cajón móvil con backdrop y seis transiciones declaradas a mano, cabecera, banner de suplantación y el hueco del contenido. Flux trae `sidebar` (con `brand`, `collapse`, `toggle`, `spacer`), `header` y `main`, que hacen lo mismo con modo oscuro y teclado resueltos.

Van juntos el layout y el menú de usuario porque no se pueden separar sin romper algo por el camino: el layout nuevo incluye el menú nuevo, y el menú viejo (`navigation-dropdown.blade.php`) lo incluye el layout viejo. Hacerlo en dos tareas dejaría una de las dos con una vista inexistente.

**Files:**
- Rewrite: `resources/views/layouts/app.blade.php`
- Create: `resources/views/layouts/user-menu.blade.php`
- Delete: `resources/views/layouts/navigation-dropdown.blade.php`, `resources/views/components/dropdown-link.blade.php`
- Modify: `resources/lang/en/app.php`, `resources/lang/es/app.php`
- Test: `tests/Feature/ShellRenderTest.php` (nuevo)

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/ShellRenderTest.php`:

```php
<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;

beforeEach(function () {
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
```

- [ ] **Step 2: Add the translation keys**

En `resources/lang/en/app.php` y `resources/lang/es/app.php`, añade al array raíz:

```php
    'appearance' => [
        'label' => 'Appearance',   // es: 'Apariencia'
        'light' => 'Light',        // es: 'Claro'
        'dark' => 'Dark',          // es: 'Oscuro'
        'system' => 'System',      // es: 'Sistema'
    ],
```

`TranslationKeysTest` las exigirá en los dos idiomas en cuanto la vista las use.

- [ ] **Step 3: Run the test and watch it fail**

Run: `./vendor/bin/pest tests/Feature/ShellRenderTest.php`
Expected: FAIL — no hay `data-flux-sidebar` ni selector de tema.

- [ ] **Step 4: Write the user menu partial first**

El layout de abajo lo incluye, así que tiene que existir antes o la vista revienta.

Crea `resources/views/layouts/user-menu.blade.php`:

```blade
<flux:dropdown position="top" align="start" class="w-full">
    <flux:profile
        :name="Auth::user()->name"
        :initials="Auth::user()->initials"
        :chevron="true"
    />

    <flux:menu>
        <flux:menu.item icon="user" :href="route('base-tenant.profile')" wire:navigate>
            {{ __('base-tenant::app.dropdown.link.profile') }}
        </flux:menu.item>

        @can('accounts.update')
            <flux:menu.item icon="cog-6-tooth" :href="route('base-tenant.settings.index')" wire:navigate>
                {{ __('base-tenant::app.dropdown.link.settings') }}
            </flux:menu.item>
        @endcan

        <flux:menu.separator />

        <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">
            {{ __('base-tenant::app.appearance.light') }}
        </flux:menu.item>
        <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">
            {{ __('base-tenant::app.appearance.dark') }}
        </flux:menu.item>
        <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">
            {{ __('base-tenant::app.appearance.system') }}
        </flux:menu.item>

        <flux:menu.separator />

        <livewire:base-tenant.logout />
    </flux:menu>
</flux:dropdown>
```

Cuatro cosas que verificar antes de darlo por bueno, porque son mi lectura y no una cita:

1. **`$flux.appearance`** es la magia de Alpine que expone Flux, respaldada por `localStorage['flux.appearance']`, con valores `light`, `dark` y `system`. Lo he verificado en `vendor/livewire/flux/dist/flux.min.js`. Comprueba que `@fluxAppearance` sigue en el `<head>`: es el script que aplica el tema guardado antes del primer pintado, y sin él la página parpadea en claro antes de ponerse oscura.
2. **Si existe `flux:menu.submenu`** (`ls vendor/livewire/flux/stubs/resources/views/flux/menu/`), agrupa los tres modos bajo él con el encabezado `__('base-tenant::app.appearance.label')`, que queda más limpio que tres entradas sueltas. Si no existe en la versión gratuita, deja los tres items y usa la clave `label` como `flux:menu.group heading` o descártala.
3. **`Auth::user()->initials`** — comprueba que existe (`grep -rn "initials" src/Models/User.php`).
4. **La ruta de ajustes.** He puesto `base-tenant.settings.index`; el desplegable actual apunta a `base-tenant.dashboard`, que parece un marcador de posición. Confirma cuál es la correcta en `routes/web.php`.

Fíjate en que **no he reproducido el encabezado con el nombre de la cuenta** que tiene el desplegable actual. Lo abre dentro de un `@if` y lo cierra en otro, que es justo el antipatrón que el spec critica en `edit-user`. Si lo quieres conservar, hazlo sin dejar etiquetas colgando entre condicionales.

- [ ] **Step 5: Rewrite the layout**

Sustituye `resources/views/layouts/app.blade.php` entero por lo siguiente. Igual que en la tarea 2: **verifica los props contra los componentes reales** (`vendor/livewire/flux/stubs/resources/views/flux/sidebar/*.blade.php`, `header.blade.php`, `main.blade.php`, `profile.blade.php`) y corrige lo que no case, informando de ello.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-900 dark:text-white">
    <flux:toast />

    <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand :href="route('base-tenant.dashboard')" :name="config('app.name', 'Laravel')" class="px-2" wire:navigate />

        @include('base-tenant::layouts.navigation-items')

        <flux:spacer />

        @include('base-tenant::layouts.user-menu')
    </flux:sidebar>

    <flux:header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />

        <flux:spacer />

        <div class="hidden sm:block">
            @livewire('base-tenant.account-switcher')
        </div>

        @livewire('base-tenant.notification-bell')
    </flux:header>

    @impersonating
        <flux:callout variant="warning" icon="exclamation-triangle" class="mx-6 mt-4 lg:mx-8">
            <flux:callout.text>
                {{ __('base-tenant::users.impersonation_banner', ['name' => Auth::user()->name]) }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('impersonate.leave')" size="sm" variant="primary">
                    {{ __('base-tenant::users.leave_impersonation') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endImpersonating

    <flux:main container>
        {{ $slot }}
    </flux:main>

    @livewireScripts
    @fluxScripts
</body>
</html>
```

Dos cosas a comprobar y decidir con criterio, informando de lo que elijas:

- **`flux:callout` y su slot de acciones.** Lee `vendor/livewire/flux/stubs/resources/views/flux/callout/index.blade.php` para ver cómo se le pasan acciones; si no admite `x-slot name="actions"`, coloca el botón como corresponda.
- **El orden de `@vite` y `@livewireStyles`.** Lo he invertido respecto al original a propósito, para que los estilos de la aplicación puedan pisar los de Livewire. Si al probarlo algo se descoloca, vuelve al orden original y dilo.

- [ ] **Step 6: Delete what the new shell replaces**

```bash
git rm resources/views/layouts/navigation-dropdown.blade.php resources/views/components/dropdown-link.blade.php
```

- [ ] **Step 7: Verify**

Run: `./vendor/bin/pest` → toda verde, incluidos `ShellRenderTest`, `TranslationKeysTest` (que exigirá las claves nuevas en los dos idiomas) y `OrphanViewsTest`.

Comprueba a ojo que no queda fontanería propia: `grep -n "x-data\|localStorage\|x-transition" resources/views/layouts/app.blade.php` no debería devolver nada.

- [ ] **Step 8: Commit**

```bash
git add -A resources/views resources/lang tests/Feature/ShellRenderTest.php
git commit -m "refactor: build the application shell on flux primitives"
```

---

### Task 4: La pantalla de invitado

`layouts/guest.blade.php` ya tiene algunas parejas claro/oscuro, heredadas de Breeze, pero incompletas: la tarjeta cambia y el logo no.

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`

- [ ] **Step 1: Rewrite the body**

Mantén la cabecera del documento tal cual (fuentes, `@vite`, `@livewireStyles`, `@fluxAppearance`) y sustituye el `<body>` por:

```blade
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    <flux:toast />

    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
        <a href="/" wire:navigate class="mb-6">
            <x-base-tenant::application-logo class="h-16 w-16 fill-current text-zinc-500 dark:text-zinc-400" />
        </a>

        <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
    @fluxScripts
</body>
```

Fíjate en que el original no monta `<flux:toast />` en la pantalla de invitado, así que un aviso lanzado desde login o registro no se ve. Añadirlo lo arregla.

- [ ] **Step 2: Verify**

Run: `./vendor/bin/pest` → toda verde.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/guest.blade.php
git commit -m "refactor: give the guest layout both appearances"
```

---

### Task 5: Guarda de parejas claro/oscuro en el armazón

Las cinco parejas del spec solo sirven si se aplican de verdad. En el armazón —y solo en el armazón, porque el resto se reescribe en los planes 3 y 4— se puede comprobar mecánicamente.

**Files:**
- Create: `tests/Feature/DarkModeTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Flux resuelve sus componentes en los dos modos; el markup propio no, y una
 * superficie clara sin su pareja oscura deja una tarjeta blanca sobre fondo
 * negro. Solo se exige en el armazón: las pantallas de gestión y los
 * formularios se reescriben en los planes siguientes.
 */
test('las superficies del armazón declaran su pareja oscura', function () {
    $raiz = dirname(__DIR__, 2);

    $armazon = [
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/guest.blade.php',
        'resources/views/layouts/navigation-items.blade.php',
        'resources/views/layouts/navigation-item.blade.php',
        'resources/views/layouts/user-menu.blade.php',
    ];

    // Utilidades que pintan una superficie o un borde y necesitan pareja.
    $patron = '/(?<![\w:-])(bg|text|border|divide)-(zinc|white)(?:-(\d{2,3}))?\b/';

    $sinPareja = [];

    foreach ($armazon as $relativa) {
        $ruta = "{$raiz}/{$relativa}";

        if (! File::exists($ruta)) {
            continue;
        }

        $contenido = File::get($ruta);

        preg_match_all($patron, $contenido, $coincidencias, PREG_SET_ORDER);

        foreach ($coincidencias as $coincidencia) {
            $clase = $coincidencia[0];

            if (! str_contains($contenido, 'dark:'.$coincidencia[1].'-')) {
                $sinPareja[] = "{$relativa}: {$clase}";
            }
        }
    }

    expect(array_unique($sinPareja))->toBe([]);
});
```

**Este test es más flojo de lo que parece y lo sé:** comprueba que en el fichero existe *alguna* variante `dark:` del mismo prefijo, no que esa clase concreta tenga su pareja en el mismo elemento. Hacerlo bien exigiría parsear atributos `class`, y un parser de Blade a medias falla más que acierta. Lo dejo así a propósito, como red de seguridad contra el olvido completo, no como prueba de corrección. **Si al implementarlo se te ocurre una comprobación igual de barata y menos ingenua, propónla en el informe antes de escribirla.**

- [ ] **Step 2: Run it**

Run: `./vendor/bin/pest tests/Feature/DarkModeTest.php`
Expected: PASS si las tareas 3, 4 y 5 hicieron su trabajo. Si falla, nombra el fichero y la clase: añade la pareja que falte.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DarkModeTest.php
git commit -m "test: require a dark counterpart for the shell surfaces"
```

---

### Task 6: Parejas claro/oscuro en las 55 vistas restantes

El armazón ya responde al selector; el contenido no. Esta tarea lo cierra.

No es un barrido ciego como el del vocabulario de color: allí se sustituía un token por otro y el resultado era comprobable con una expresión regular. Aquí hay que **añadir** una clase junto a otra, y solo si esa misma propiedad no tiene ya una variante oscura en el mismo atributo. Un `perl -pi` no puede saberlo y duplicaría parejas dentro de `@class([...])`, de bindings `:class` de Alpine y de clases construidas por concatenación.

**Files:**
- Modify: las vistas bajo `resources/views/` que declaren superficies claras
- Modify: `tests/Feature/DarkModeTest.php` (ampliar el ámbito)

- [ ] **Step 1: Write the transformation as a script, not a regex**

Escribe un script de un solo uso (fuera del repositorio, en un directorio temporal) que para cada fichero `.blade.php`:

1. Localice cada atributo de clases: `class="..."`, `class='...'`, y los arrays de `@class([...])`.
2. Tokenice su contenido por espacios.
3. Para cada token que aparezca en la tabla de abajo, añada su pareja **solo si** en ese mismo atributo no hay ya un token que empiece por `dark:` con el mismo prefijo de propiedad (`bg-`, `text-`, `border-`, `divide-`).
4. Deje intactos los tokens que no estén en la tabla, el orden de los demás, y cualquier interpolación Blade que haya dentro del atributo.

Tabla de equivalencias, medida sobre el árbol actual:

| Claro | Oscuro |
|---|---|
| `bg-white` | `dark:bg-zinc-900` |
| `bg-zinc-50` | `dark:bg-zinc-950` |
| `bg-zinc-100` | `dark:bg-zinc-800` |
| `text-zinc-900` | `dark:text-white` |
| `text-zinc-800` | `dark:text-zinc-100` |
| `text-zinc-700` | `dark:text-zinc-200` |
| `text-zinc-600` | `dark:text-zinc-300` |
| `text-zinc-500` | `dark:text-zinc-400` |
| `text-zinc-400` | `dark:text-zinc-500` |
| `border-zinc-100` | `dark:border-zinc-800` |
| `border-zinc-200` | `dark:border-zinc-700` |
| `border-zinc-300` | `dark:border-zinc-600` |
| `divide-zinc-200` | `dark:divide-zinc-700` |
| `bg-accent-50` | `dark:bg-accent-950/40` |
| `bg-accent-100` | `dark:bg-accent-900/40` |
| `text-accent-600` | `dark:text-accent-400` |
| `text-accent-700` | `dark:text-accent-300` |
| `bg-{estado}-100` | `dark:bg-{estado}-900/30` |
| `text-{estado}-600` | `dark:text-{estado}-400` |
| `text-{estado}-800` | `dark:text-{estado}-300` |
| `border-{estado}-200` | `dark:border-{estado}-800` |

donde `{estado}` es `success`, `warning`, `danger` o `info`.

**Deliberadamente fuera de la tabla**, porque no son superficies claras sino elementos que ya son oscuros o de color sólido, y emparejarlos los rompería: `text-white`, `bg-accent-600`, `bg-zinc-600`, `bg-zinc-900`, y los tokens planos `bg-success` / `text-success` y equivalentes. Si al revisar el diff ves alguno que sí necesitaba pareja, arréglalo a mano y dilo.

- [ ] **Step 2: Run it and inspect the blast radius**

Ejecuta el script y comprueba el alcance antes de dar nada por bueno:

Run: `git diff --stat | tail -1`

Cada línea cambiada debe ser una línea con clases. Revisa a ojo el diff completo de tres ficheros de perfiles distintos: uno de tabla (`livewire/user-manager.blade.php`), uno de formulario (`livewire/edit-user.blade.php`) y uno de autenticación (`livewire/auth/login.blade.php`). Si aparece un cambio que no sea una clase añadida, **párate y repórtalo**.

- [ ] **Step 3: Widen the guard to every view**

En `tests/Feature/DarkModeTest.php`, sustituye la lista fija de ficheros del armazón por un recorrido de todo `resources/views`, y comprueba la pareja **dentro del mismo atributo de clases**, no en el fichero entero: reutiliza el tokenizador del script del paso 1, portado al test. La versión del armazón se conformaba con que existiera alguna `dark:` en el fichero; con 55 vistas esa laxitud no vale nada.

Excluye de la comprobación los tokens de la lista de "deliberadamente fuera de la tabla", y documenta en el docblock por qué cada uno está exento.

- [ ] **Step 4: Verify**

Run: `./vendor/bin/pest` → toda verde.

Comprueba además que no se ha duplicado nada: `grep -rc "dark:bg-zinc-900 dark:bg-zinc-900\|dark:text-white dark:text-white" resources/views --include='*.blade.php' | grep -v ':0'` no debe devolver nada.

- [ ] **Step 5: Commit**

```bash
git add -A resources/views tests/Feature/DarkModeTest.php
git commit -m "feat: give every view a dark counterpart"
```

---

## Definición de terminado

- [ ] `./vendor/bin/pest` en verde, con los cuatro tests nuevos (`NavigationRenderTest`, `ShellRenderTest`, `DarkModeTest`, y `OrphanViewsTest` endurecido).
- [ ] `grep -rn "x-data\|localStorage\|x-transition" resources/views/layouts/` no devuelve nada: no queda fontanería propia en el armazón.
- [ ] `resources/views/components/` contiene solo `application-logo` y los componentes de formulario que los planes 3 y 4 van a retirar.
- [ ] El selector de tema cambia el aspecto sin recargar y sobrevive a un refresco.
- [ ] `./vendor/bin/pint --dirty` sin cambios pendientes.

## Comprobación manual, que ninguna prueba cubre

La suite no ve píxeles. Antes de dar el plan por cerrado, con la aplicación levantada:

1. Plegar la barra lateral en escritorio y refrescar: sigue plegada.
2. Abrir el cajón en móvil, navegar a otra sección: se cierra solo.
3. Cambiar a oscuro y recorrer las pantallas principales —panel, usuarios, editar usuario, roles, actividad, perfil, login—: armazón y contenido cambian juntos, no queda ninguna tarjeta blanca sobre fondo oscuro ni texto ilegible.
4. Suplantar a un usuario: el banner se ve, en ámbar, y el botón de salir funciona.
5. Recorrer la navegación con el teclado: el foco entra en la barra lateral, recorre las entradas y sale.

## Qué queda para los siguientes planes

| Plan | Contenido | Sección del spec |
|---|---|---|
| 3 | Patrón de tabla en usuarios, cuentas, invitaciones, actividad y features; trait `InteractsWithTable`; parejas claro/oscuro de esas pantallas | 2 |
| 4 | Patrón de formulario a dos columnas; retirada de los componentes que sustituye Flux; confirmaciones con `flux:modal`; parejas claro/oscuro de los formularios | 3 y 5 |
