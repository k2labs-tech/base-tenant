# Patrón de formulario — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que los formularios del paquete se lean como una hoja de ajustes y estén construidos con Flux, retirando los diez componentes propios que quedan.

**Architecture:** Secciones apiladas a dos columnas —título y descripción a la izquierda, campos a la derecha, con ancho de lectura acotado—, cada bloque con su propio `<form>` y su botón de guardar. Los campos pasan a `flux:field`, que agrupa etiqueta, descripción, error y control. Las confirmaciones destructivas, a `flux:modal`.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI v2.15 (gratuito), Tailwind v4, Pest 3.

**Alcance:** Plan 4 de 4 del rediseño de `docs/superpowers/specs/2026-08-02-rediseno-ux-ui-design.md`, secciones 3 y 5. Cierra el rediseño.

**Estado de partida, medido hoy:** quedan diez componentes anónimos propios, con 251 usos entre todos. `input-label` (67), `input-error` (65), `primary-button` (48), `text-input` (44), `secondary-button` (10), `modal` (6), `danger-button` (4), `action-message` (4), `auth-session-status` (2) y `application-logo` (1). Los nueve primeros tienen equivalente directo en Flux; `application-logo` es marca propia y se queda. Y hay tres vistas que confirman acciones destructivas con `wire:confirm`, el diálogo nativo del navegador: `role-manager`, `navigation-manager` y `notifications/index`.

**Por qué `edit-user` va primero:** son 272 líneas que hacen cuatro cosas —datos, contraseña, preferencias, roles y cuentas— y abren la etiqueta `<form>` dentro de un `@if` para cerrarla en otro `@endif` según sea alta o edición. No llega a anidar formularios en ejecución, lo comprobé, pero es markup que se rompe en cuanto alguien toca una condición, y ninguna herramienta lo avisa.

---

### Task 1: `edit-user`, la implementación de referencia

**Files:**
- Split: `resources/views/livewire/edit-user.blade.php` → `edit-user.blade.php` y `create-user.blade.php`
- Modify: `src/Livewire/EditUser.php`
- Modify: `resources/lang/{en,es}/users.php`
- Create: `tests/Feature/UserFormTest.php`

- [ ] **Step 1: Write the failing test**

Cubre lo que hoy no cubre nada: que alta y edición son caminos distintos, que cada bloque guarda por separado, y que un campo sin permiso se deshabilita en vez de desaparecer.

```php
<?php

declare(strict_types=1);

use Base\Tenant\Livewire\EditUser;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('guardar los datos personales no toca la contraseña', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $otro = $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe']);

    $this->actingAsTenant($admin, $cuenta);

    $anterior = $otro->password;

    Livewire::test(EditUser::class, ['user' => $otro])
        ->set('name', 'Zoe Ruiz')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($otro->fresh()->name)->toBe('Zoe Ruiz')
        ->and($otro->fresh()->password)->toBe($anterior);
});

test('el alta valida la contraseña y la edición no la exige', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $otro = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $otro])
        ->set('name', 'Sin contraseña')
        ->call('updateProfileInformation')
        ->assertHasNoErrors('password');
});
```

Añade un tercer caso para el estado deshabilitado en cuanto sepas qué permiso lo gobierna: léelo en `src/Livewire/EditUser.php` antes de escribirlo, no lo supongas.

- [ ] **Step 2: Split create from edit**

`EditUser` sirve hoy dos pantallas con un `$isCreateMode`. Sepáralas en dos vistas y deja que el componente elija cuál renderiza. Es lo que permite que cada una tenga sus propios `<form>` bien formados, y lo que elimina las etiquetas abiertas entre condicionales.

Si al leerlo ves que separar el componente entero —y no solo la vista— es más limpio, propónlo antes de hacerlo: es un cambio mayor que lo que pide esta tarea y prefiero decidirlo contigo.

- [ ] **Step 3: Write the two-column section pattern**

La estructura que copian todas las pantallas siguientes:

```blade
<div class="space-y-10">
    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::users.profile_information') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.profile_information_description') }}</flux:subheading>
        </div>

        <form wire:submit="updateProfileInformation" class="md:col-span-2 max-w-xl space-y-4">
            <flux:input wire:model="name" :label="__('base-tenant::users.name')" required />
            <flux:input wire:model="email" type="email" :label="__('base-tenant::users.email')" required />

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updateProfileInformation">
                    {{ __('base-tenant::users.save_profile') }}
                </span>
                <span wire:loading wire:target="updateProfileInformation">
                    {{ __('base-tenant::common.saving') }}
                </span>
            </flux:button>
        </form>
    </section>

    <flux:separator />

    {{-- Siguiente sección: contraseña, preferencias, roles… --}}
</div>
```

**Verifica contra los stubs** (`vendor/livewire/flux/stubs/resources/views/flux/input/*`, `field.blade.php`, `select/*`, `checkbox/*`, `button/*`, `separator.blade.php`) que `:label`, `:description`, `required` y el manejo automático de errores funcionan como escribo. `flux:input` monta su propio `flux:field` cuando recibe `label` — confírmalo, porque de ello depende que `input-label` e `input-error` puedan desaparecer. Y recuerda: **nunca un `@if ... @endif` dentro de la lista de atributos de un componente Flux**; usa atributos vinculados (`:disabled="$noPuedeEditar"`).

Descripciones nuevas por sección, en los dos idiomas. Son el motivo de elegir esta disposición: es el único sitio del paquete donde caben.

- [ ] **Step 4: Verify and commit**

`./vendor/bin/pest` verde, incluidos `DarkModeTest` y `TranslationKeysTest`.

```bash
git commit -m "refactor: rebuild the user form as two-column sections"
```

---

### Task 2: `edit-account`, perfil y preferencias

**Files:**
- Rewrite: `resources/views/livewire/edit-account.blade.php`, `resources/views/livewire/preferences.blade.php`, `resources/views/livewire/profile/update-profile-information-form.blade.php`, `resources/views/livewire/profile/update-password-form.blade.php`, `resources/views/livewire/profile/delete-user-form.blade.php`
- Create: `tests/Feature/ProfileFormTest.php`

Aplica la estructura de la tarea 1. Notas por pantalla:

- **`preferences`** tiene siete desplegables (idioma, moneda, decimales, zona horaria, separadores, formatos de fecha y hora). Agrúpalos en dos secciones —regional y formato de números— en vez de una lista de siete campos.
- **`delete-user-form`** es destructivo: su confirmación pasa a `flux:modal` con el botón en variante `danger`, y el modal nombra lo que se va a borrar.
- **`profile.blade.php`** monta cinco componentes Livewire en cinco tarjetas. Con la disposición nueva, cada uno trae su propia sección: la vista contenedora se queda casi vacía, que es lo correcto.

- [ ] **Step 1: Test, fallo, implementación, verificación, commit**

```bash
git commit -m "refactor: rebuild the account and profile forms"
```

---

### Task 3: Autenticación

**Files:**
- Rewrite: las siete vistas de `resources/views/livewire/auth/`
- Create: `tests/Feature/AuthFormTest.php`

Login, registro, recuperación, restablecimiento, confirmación, verificación de email y cambio forzado de contraseña. Aquí **no** aplica la disposición a dos columnas: son formularios de tres campos dentro de la tarjeta centrada del layout de invitado. Lo que cambia es que pasan a `flux:input`, `flux:button` y `flux:callout` para los avisos de sesión, que es lo que retira `auth-session-status`.

Cuida dos cosas: el estado de carga en el botón de enviar —un login sin respuesta visible invita a pulsar dos veces— y que los mensajes de error de credenciales sigan saliendo donde salían.

- [ ] **Step 1: Test, fallo, implementación, verificación, commit**

```bash
git commit -m "refactor: rebuild the authentication forms with flux"
```

---

### Task 4: Confirmaciones destructivas

**Files:**
- Modify: `resources/views/livewire/role-manager.blade.php`, `resources/views/livewire/navigation-manager.blade.php`, `resources/views/livewire/notifications/index.blade.php`
- Create: `tests/Feature/DestructiveConfirmationTest.php`

Las tres usan `wire:confirm`, el diálogo nativo del navegador: sin traducir el botón, imposible de estilar, y en algunos navegadores suprimible por el usuario. Pasan a `flux:modal`, nombrando el objeto —«Eliminar el rol *auditor*»— y con el botón en variante `danger`.

Añade una guarda: un test que falle si aparece `wire:confirm` en cualquier vista del paquete. Es una regla que se olvida con facilidad y cuesta un `grep`.

```bash
git commit -m "refactor: confirm destructive actions with a modal instead of the browser dialog"
```

---

### Task 5: Retirar los nueve componentes que Flux sustituye

**Files:**
- Delete: `input-label`, `input-error`, `primary-button`, `text-input`, `secondary-button`, `modal`, `danger-button`, `action-message`, `auth-session-status` bajo `resources/views/components/`
- Modify: `docs/FRONTEND.md`

Solo cuando las tareas 1 a 4 hayan dejado sus usos a cero. Compruébalo con el patrón que distingue nombres con prefijo común, que es donde falló la primera vez:

```bash
for c in input-label input-error primary-button text-input secondary-button modal danger-button action-message auth-session-status; do
  echo "$c: $(grep -rhoP "x-base-tenant::$c(?![a-z-])" resources/views | wc -l)"
done
```

Todos a cero antes de borrar nada. Después, `OrphanViewsTest` en pasadas hasta que salga limpio —encuentra la cabeza de una cadena muerta, no la cadena entera—, y `ViewCompilationTest` para probar que ninguna vista superviviente referencia lo borrado. Recuerda que ese test compila pero no evalúa: si una vista falla solo al renderizarse, lo dirá un test de renderizado, no él.

`docs/FRONTEND.md` lista los componentes vivos; déjala con `application-logo` y lo que quede.

```bash
git commit -m "chore: remove the components flux replaces"
```

---

### Task 6: Cerrar el rediseño

**Files:**
- Modify: `docs/superpowers/specs/2026-08-02-rediseno-ux-ui-design.md`
- Modify: `docs/FRONTEND.md`

Repasa los siete criterios de aceptación del spec y anota junto a cada uno si se cumplió, y si no, por qué. Los que sé hoy que no se cumplirán tal como se escribieron:

- El spec decía que sobrevivirían dos componentes propios, `application-logo` y `menu-tree`. `menu-tree` se borró en el plan 2: era el renderizador de la barra lateral, no el árbol ordenable del editor de menús, que tiene su propio markup. Queda uno.
- El spec contaba 36 ficheros a borrar; entre cadenas de huérfanos y componentes que aparecieron después, la cifra real es mayor. Da la definitiva, contada.
- El spec afirmaba que Flux define `accent`. No lo hace: define tres tokens planos y ninguna escala numérica. El paquete la define ahora en violeta.

Un spec que se queda mintiendo sobre lo que se construyó es peor que no tenerlo.

```bash
git commit -m "docs: reconcile the redesign spec with what was built"
```

---

## Definición de terminado

- [ ] `./vendor/bin/pest` en verde.
- [ ] `resources/views/components/` contiene solo `application-logo`.
- [ ] Ni un `wire:confirm` en el paquete, y un test que lo impide.
- [ ] Ningún `<form>` abierto dentro de un `@if` y cerrado en otro.
- [ ] Todos los campos con su etiqueta, su descripción cuando aporta, y su error junto al campo.
- [ ] `./vendor/bin/pint --dirty` sin cambios pendientes.

## Comprobación manual

1. Guardar cada bloque de `edit-user` por separado y comprobar que solo cambia lo suyo.
2. Intentar borrar un rol: sale un modal, no el diálogo del navegador, y nombra el rol.
3. Enviar el login con credenciales malas: el error sale junto al campo, no en un toast.
4. Recorrer un formulario largo con el teclado: el orden de foco sigue el orden visual.
5. En oscuro: formularios, modales y estados de error.
