<?php

declare(strict_types=1);

use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * El formulario de usuario tenía cuatro bloques independientes y ninguna
 * prueba: nada comprobaba que guardar uno dejase los otros en paz, ni que la
 * creación y la edición validasen distinto, ni que la cuenta que un usuario
 * corriente no puede elegir siguiera estando a la vista.
 *
 * Los tres son fallos silenciosos. Un bloque que arrastra los campos de otro
 * no da error: guarda de más. Una regla `required` colada en la edición no
 * rompe la página: bloquea el botón. Y un campo que desaparece en vez de
 * deshabilitarse deja al usuario sin saber en qué cuenta está creando a nadie.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
});

test('guardar los datos personales no toca la contraseña', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user', [
        'password' => Hash::make('contrasena-original'),
    ]);

    $hashOriginal = $objetivo->password;

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Nombre Nuevo')
        ->set('password', 'otra-contrasena-distinta')
        ->set('password_confirmation', 'otra-contrasena-distinta')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $objetivo->refresh();

    expect($objetivo->name)->toBe('Nombre Nuevo');
    expect($objetivo->password)->toBe($hashOriginal);
});

test('la edición no exige contraseña', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Sin Contraseña')
        ->set('password', null)
        ->set('password_confirmation', null)
        ->call('updateProfileInformation')
        ->assertHasNoErrors('password');

    expect($objetivo->refresh()->name)->toBe('Sin Contraseña');
});

test('cambiar la contraseña no toca los datos personales', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user', ['name' => 'Nombre Original']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Nombre Que No Debe Guardarse')
        ->set('password', 'contrasena-nueva')
        ->set('password_confirmation', 'contrasena-nueva')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $objetivo->refresh();

    expect($objetivo->name)->toBe('Nombre Original');
    expect(Hash::check('contrasena-nueva', $objetivo->password))->toBeTrue();
});

test('guardar las preferencias no toca los datos personales', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user', [
        'name' => 'Nombre Original',
        'timezone' => 'UTC',
    ]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Nombre Que No Debe Guardarse')
        ->set('timezone', 'Europe/Madrid')
        ->set('date_format', 'd/m/Y')
        ->call('updatePreferences')
        ->assertHasNoErrors();

    $objetivo->refresh();

    expect($objetivo->name)->toBe('Nombre Original');
    expect($objetivo->timezone)->toBe('Europe/Madrid');
    expect($objetivo->date_format)->toBe('d/m/Y');
});

test('guardar los roles no toca los datos personales', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user', ['name' => 'Nombre Original']);

    $financiero = Role::query()->where('name', 'customer-finance')->firstOrFail();

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Nombre Que No Debe Guardarse')
        ->set('selectedRoles', [$financiero->getKey()])
        ->call('updateRoles')
        ->assertHasNoErrors();

    $objetivo->refresh();

    expect($objetivo->name)->toBe('Nombre Original');
    expect($objetivo->rolesForAccount($cuenta)->pluck('name')->all())->toBe(['customer-finance']);
});

test('la creación exige contraseña y al menos un rol', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    // `saveNewUser` es lo que envía el formulario de alta; pedirle lo mismo que
    // a la edición sería el fallo que esta prueba persigue.
    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Persona Nueva')
        ->set('email', 'persona.nueva@ejemplo.test')
        ->set('password', null)
        ->set('password_confirmation', null)
        ->set('selectedRoles', [])
        ->call('saveNewUser')
        ->assertHasErrors(['password', 'selectedRoles']);

    expect(User::query()->where('email', 'persona.nueva@ejemplo.test')->exists())->toBeFalse();
});

test('el alta guarda datos, contraseña, preferencias y roles de una vez', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $financiero = Role::query()->where('name', 'customer-finance')->firstOrFail();

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Persona Nueva')
        ->set('email', 'persona.nueva@ejemplo.test')
        ->set('phone', '600123456')
        ->set('password', 'contrasena-inicial')
        ->set('password_confirmation', 'contrasena-inicial')
        ->set('timezone', 'Europe/Madrid')
        ->set('date_format', 'd/m/Y')
        ->set('selectedRoles', [$financiero->getKey()])
        ->call('saveNewUser')
        ->assertHasNoErrors();

    $creado = User::query()->where('email', 'persona.nueva@ejemplo.test')->firstOrFail();

    expect($creado->name)->toBe('Persona Nueva');
    expect($creado->phone)->toBe('600123456');
    expect($creado->timezone)->toBe('Europe/Madrid');
    expect($creado->date_format)->toBe('d/m/Y');
    expect(Hash::check('contrasena-inicial', $creado->password))->toBeTrue();
    expect($creado->rolesForAccount($cuenta)->pluck('name')->all())->toBe(['customer-finance']);
});

/**
 * Un grupo de casillas sin etiqueta no monta el campo de Flux, y sin campo no
 * hay hueco donde pintar el error: la lista de roles se quedaba rechazando el
 * envío sin decir por qué.
 */
test('el error de los roles se ve en pantalla', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(EditUser::class, ['user' => $objetivo])
        ->set('name', 'Persona Nueva')
        ->set('email', 'persona.nueva@ejemplo.test')
        ->set('password', 'contrasena-inicial')
        ->set('password_confirmation', 'contrasena-inicial')
        ->set('selectedRoles', [])
        ->call('saveNewUser')
        ->assertHasErrors('selectedRoles')
        ->assertSeeHtml('data-flux-error')
        ->assertSee(__('validation.required', ['attribute' => 'selected roles']));
});

/**
 * Devuelve la etiqueta `<select>` que gobierna un campo, sin su lista de
 * clases: Flux mete ahí variantes como `dark:disabled:bg-white/[7%]`, y
 * buscar «disabled» sobre la etiqueta entera daría por deshabilitado cualquier
 * `<select>` del paquete.
 */
function etiquetaSelect(string $html, string $campo): string
{
    preg_match_all('/<select\b[^>]*>/', $html, $encontrados);

    foreach ($encontrados[0] as $etiqueta) {
        if (str_contains($etiqueta, $campo)) {
            return (string) preg_replace('/\sclass="[^"]*"/', '', $etiqueta);
        }
    }

    return '';
}

test('quien no puede elegir cuenta la ve deshabilitada, no escondida', function () {
    $cuenta = $this->createAccount(['name' => 'Cuenta Visible']);
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $respuesta = $this->get(route('base-tenant.users.create'))->assertOk();

    $etiqueta = etiquetaSelect($respuesta->getContent(), 'selected_account_id');

    expect($etiqueta)->not->toBe('', 'el alta esconde el campo de cuenta en vez de deshabilitarlo');
    expect($etiqueta)->toContain('disabled');

    $respuesta->assertSee('Cuenta Visible');
});

test('un administrador del sistema sí puede elegir cuenta', function () {
    $cuenta = $this->createAccount(['name' => 'Cuenta Visible']);
    $otra = $this->createAccount(['name' => 'Otra Cuenta']);
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($admin, $cuenta);

    $respuesta = $this->get(route('base-tenant.users.create'))->assertOk();

    $etiqueta = etiquetaSelect($respuesta->getContent(), 'selected_account_id');

    expect($etiqueta)->not->toBe('');
    expect($etiqueta)->not->toContain('disabled');

    $respuesta->assertSee($otra->name);
});

/**
 * El fichero anterior abría un `<form>` dentro de un `@if` y lo cerraba dentro
 * de otro. No anidaba formularios en tiempo de ejecución, pero nada lo
 * garantizaba: bastaba tocar una condición. Esto lo garantiza.
 */
test('cada pantalla abre y cierra sus formularios de forma equilibrada', function (string $ruta) {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    $url = $ruta === 'base-tenant.users.create'
        ? route($ruta)
        : route($ruta, ['user' => $objetivo]);

    $html = $this->get($url)->assertOk()->getContent();

    preg_match_all('/<form\b|<\/form>/', $html, $encontrados, PREG_OFFSET_CAPTURE);

    $profundidad = 0;
    $maxima = 0;
    $aperturas = 0;

    foreach ($encontrados[0] as [$etiqueta, $_]) {
        if ($etiqueta === '</form>') {
            $profundidad--;
        } else {
            $profundidad++;
            $aperturas++;
        }

        $maxima = max($maxima, $profundidad);

        expect($profundidad)->toBeGreaterThanOrEqual(0, 'se cierra un <form> que nadie abrió');
    }

    expect($aperturas)->toBeGreaterThan(0);
    expect($profundidad)->toBe(0, 'queda un <form> sin cerrar');
    expect($maxima)->toBe(1, 'hay un <form> dentro de otro');
})->with(['base-tenant.users.create', 'base-tenant.users.edit']);

test('la edición ofrece un botón por bloque', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $objetivo = $this->createUser($cuenta, 'customer-user');

    $this->actingAsTenant($admin, $cuenta);

    $respuesta = $this->get(route('base-tenant.users.edit', ['user' => $objetivo]))->assertOk();

    $respuesta->assertSee('wire:submit="updateProfileInformation"', false);
    $respuesta->assertSee('wire:submit="updatePassword"', false);
    $respuesta->assertSee('wire:submit="updatePreferences"', false);
    $respuesta->assertSee('wire:submit="updateRoles"', false);
});

test('el alta envía un único formulario', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $respuesta = $this->get(route('base-tenant.users.create'))->assertOk();

    $respuesta->assertSee('wire:submit="saveNewUser"', false);
    $respuesta->assertDontSee('wire:submit="updatePassword"', false);
    $respuesta->assertDontSee('wire:submit="updatePreferences"', false);
    $respuesta->assertDontSee('wire:submit="updateRoles"', false);
});
