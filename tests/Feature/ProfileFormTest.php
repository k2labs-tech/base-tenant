<?php

declare(strict_types=1);

use Base\Tenant\Livewire\EditAccount;
use Base\Tenant\Livewire\Preferences;
use Base\Tenant\Livewire\Profile\DeleteUserForm;
use Base\Tenant\Livewire\Profile\UpdatePasswordForm;
use Base\Tenant\Livewire\Profile\UpdateProfileInformationForm;
use Base\Tenant\Models\User;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Los formularios de perfil, preferencias y cuenta nunca tuvieron pruebas. Lo
 * que se comprueba aquí no es que guarden —eso ya lo hacían— sino lo que se
 * pierde al reescribir el marcado:
 *
 * - Que cada mensaje de validación siga llegando a la pantalla. Un campo de
 *   Flux sin etiqueta no monta su campo y, con él, se lleva el hueco del error:
 *   el formulario rechaza el envío sin decir por qué. Es el mismo fallo que
 *   apareció en el grupo de casillas de roles.
 * - Que el borrado de cuenta siga pidiendo la contraseña y siga diciendo qué
 *   se borra.
 * - Que las preferencias se agrupen y no vuelvan a ser una lista de nueve
 *   desplegables sin jerarquía.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
});

test('los datos del perfil se guardan', function () {
    $usuario = $this->createUser(null, null, ['name' => 'Nombre Viejo']);

    $this->actingAs($usuario);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('name', 'Nombre Nuevo')
        ->set('email', 'nuevo@ejemplo.test')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $usuario->refresh();

    expect($usuario->name)->toBe('Nombre Nuevo');
    expect($usuario->email)->toBe('nuevo@ejemplo.test');
});

test('cambiar el correo lo deja sin verificar otra vez', function () {
    $usuario = $this->createUser(null, null, ['email_verified_at' => now()]);

    $this->actingAs($usuario);

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('email', 'otro@ejemplo.test')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($usuario->refresh()->email_verified_at)->toBeNull();
});

/**
 * El aviso sólo aparece si el modelo de usuario implementa `MustVerifyEmail`, y
 * el del paquete NO lo hace: con él el bloque nunca se pinta. Quien lo ve es la
 * aplicación anfitriona que cambia el modelo, así que aquí se autentica una
 * subclase que sí lo implementa; sin ella la prueba pasaría en verde sin
 * ejercitar una sola línea del bloque.
 */
test('el aviso de correo sin verificar sigue ofreciendo reenviar', function () {
    $usuario = $this->createUser(null, null, ['email_verified_at' => null]);

    $this->actingAs(UsuarioQueVerificaCorreo::query()->findOrFail($usuario->getKey()));

    Livewire::test(UpdateProfileInformationForm::class)
        ->assertSee(__('base-tenant::app.profile.email_unverified'))
        ->assertSeeHtml('wire:click.prevent="sendVerification"');
});

test('con el modelo del paquete el aviso de verificación no se pinta', function () {
    $usuario = $this->createUser(null, null, ['email_verified_at' => null]);

    $this->actingAs($usuario);

    Livewire::test(UpdateProfileInformationForm::class)
        ->assertDontSee(__('base-tenant::app.profile.email_unverified'));
});

/**
 * El barrido de mensajes: cada caso es un campo que hoy enseña su error y que
 * una reescritura puede dejar mudo.
 *
 * @param  callable(): array{0: class-string, 1: array<string, mixed>, 2: string, 3: string}  $caso
 */
test('cada error de validación llega a la pantalla', function (string $componente, array $valores, string $metodo, string $campo) {
    $usuario = $this->createUser(null, null, [
        'name' => 'Titular',
        'email' => 'titular@ejemplo.test',
        'password' => Hash::make('contrasena-correcta'),
    ]);

    User::factory()->create(['email' => 'ocupado@ejemplo.test']);

    $this->actingAs($usuario);

    $componenteVivo = Livewire::test($componente);

    foreach ($valores as $propiedad => $valor) {
        $componenteVivo->set($propiedad, $valor);
    }

    $componenteVivo->call($metodo)->assertHasErrors($campo);

    $mensaje = $componenteVivo->errors()->first($campo);

    expect($mensaje)->not->toBe('');

    // No basta con que el error exista en el componente: tiene que estar en el
    // HTML que se devuelve, que es lo que se pierde al quitar una etiqueta.
    $componenteVivo->assertSeeHtml('data-flux-error');
    $componenteVivo->assertSee($mensaje);
})->with([
    'nombre de perfil vacío' => [UpdateProfileInformationForm::class, ['name' => ''], 'updateProfileInformation', 'name'],
    'correo de perfil ya usado' => [UpdateProfileInformationForm::class, ['email' => 'ocupado@ejemplo.test'], 'updateProfileInformation', 'email'],
    'contraseña actual equivocada' => [UpdatePasswordForm::class, ['current_password' => 'no-es-esta', 'password' => 'contrasena-larga', 'password_confirmation' => 'contrasena-larga'], 'updatePassword', 'current_password'],
    'contraseña nueva sin confirmar' => [UpdatePasswordForm::class, ['current_password' => 'contrasena-correcta', 'password' => 'contrasena-larga', 'password_confirmation' => 'otra-cosa'], 'updatePassword', 'password'],
    'zona horaria inválida' => [Preferences::class, ['timezone' => 'Marte/Olympus'], 'updatePreferences', 'timezone'],
    'moneda inválida' => [Preferences::class, ['currency' => 'XXX'], 'updatePreferences', 'currency'],
    'contraseña de borrado en blanco' => [DeleteUserForm::class, ['password' => ''], 'deleteUser', 'password'],
    'contraseña de borrado equivocada' => [DeleteUserForm::class, ['password' => 'no-es-esta'], 'deleteUser', 'password'],
]);

test('la contraseña se cambia dando la actual', function () {
    $usuario = $this->createUser(null, null, ['password' => Hash::make('contrasena-correcta')]);

    $this->actingAs($usuario);

    Livewire::test(UpdatePasswordForm::class)
        ->set('current_password', 'contrasena-correcta')
        ->set('password', 'contrasena-nueva-larga')
        ->set('password_confirmation', 'contrasena-nueva-larga')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('contrasena-nueva-larga', $usuario->refresh()->password))->toBeTrue();
});

test('las preferencias se guardan', function () {
    $usuario = $this->createUser(null, null, ['locale' => 'en']);

    $this->actingAs($usuario);

    Livewire::test(Preferences::class)
        ->set('locale', 'es')
        ->set('currency', 'EUR')
        ->set('timezone', 'Europe/Madrid')
        ->set('decimal_places', 3)
        ->set('decimals_separator', ',')
        ->set('thousands_separator', '.')
        ->set('date_format', 'd/m/Y')
        ->set('time_format', 'H:i')
        ->call('updatePreferences')
        ->assertHasNoErrors();

    $usuario->refresh();

    expect($usuario->locale)->toBe('es');
    expect($usuario->currency)->toBe('EUR');
    expect($usuario->timezone)->toBe('Europe/Madrid');
    expect($usuario->decimal_places)->toBe(3);
    expect($usuario->decimals_separator)->toBe(',');
    expect($usuario->thousands_separator)->toBe('.');
});

/**
 * Nueve desplegables seguidos no son un formulario, son un inventario. El
 * agrupado es la razón de esta pantalla, así que se comprueba.
 */
test('las preferencias se agrupan en bloques con su explicación', function () {
    $usuario = $this->createUser();

    $this->actingAs($usuario);

    Livewire::test(Preferences::class)
        ->assertSee(__('base-tenant::app.profile.regional'))
        ->assertSee(__('base-tenant::app.profile.regional_description'))
        ->assertSee(__('base-tenant::app.profile.number_formatting'))
        ->assertSee(__('base-tenant::app.profile.number_formatting_description'))
        ->assertSee(__('base-tenant::app.profile.email_notifications'))
        ->assertSee(__('base-tenant::app.profile.email_notifications_description'));
});

/**
 * El bloque de notificaciones venía con el texto en inglés escrito a pelo en la
 * plantilla, así que en español salía en inglés y nadie lo veía.
 */
test('el bloque de notificaciones habla el idioma de la aplicación', function () {
    $usuario = $this->createUser();

    $this->actingAs($usuario);

    app()->setLocale('es');

    Livewire::test(Preferences::class)
        ->assertDontSee('Email Notifications')
        ->assertDontSee('Daily Notification Summary')
        ->assertDontSee('Always enabled')
        ->assertSee(__('base-tenant::app.profile.daily_notification_summary'));
});

test('el resumen diario puede heredar de la cuenta', function () {
    $cuenta = $this->createAccount(['daily_notification_summary' => true]);
    $usuario = $this->createUser($cuenta, null, ['daily_notification_summary' => true]);

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(Preferences::class)
        ->set('daily_notification_summary', '')
        ->call('updatePreferences')
        ->assertHasNoErrors();

    expect($usuario->refresh()->daily_notification_summary)->toBeNull();
});

test('el modal de borrado dice a quién se borra y pide la contraseña', function () {
    $usuario = $this->createUser(null, null, [
        'name' => 'Titular Que Se Va',
        'email' => 'titular.que.se.va@ejemplo.test',
    ]);

    $this->actingAs($usuario);

    Livewire::test(DeleteUserForm::class)
        ->assertSee('Titular Que Se Va')
        ->assertSee('titular.que.se.va@ejemplo.test')
        ->assertSeeHtml('wire:model="password"')
        // Flux consume `variant` como prop, así que no queda en el HTML: lo que
        // sí queda es el color que ese variant pinta.
        ->assertSeeHtml('bg-red-500');
});

test('la contraseña correcta borra la cuenta', function () {
    $usuario = $this->createUser(null, null, ['password' => Hash::make('contrasena-correcta')]);
    $id = $usuario->getKey();

    $this->actingAs($usuario);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'contrasena-correcta')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(User::query()->whereKey($id)->exists())->toBeFalse();
});

test('la contraseña equivocada no borra nada', function () {
    $usuario = $this->createUser(null, null, ['password' => Hash::make('contrasena-correcta')]);
    $id = $usuario->getKey();

    $this->actingAs($usuario);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'no-es-esta')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(User::query()->whereKey($id)->exists())->toBeTrue();
});

test('la cuenta se guarda', function () {
    $cuenta = $this->createAccount(['name' => 'Cuenta Vieja']);
    $usuario = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(EditAccount::class, ['account' => $cuenta])
        ->set('name', 'Cuenta Nueva')
        ->set('city', 'Barcelona')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $cuenta->refresh();

    expect($cuenta->name)->toBe('Cuenta Nueva');
    expect($cuenta->city)->toBe('Barcelona');
});

test('el nombre de la cuenta vacío se queja en pantalla', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($usuario, $cuenta);

    $componente = Livewire::test(EditAccount::class, ['account' => $cuenta])
        ->set('name', '')
        ->call('saveAccount')
        ->assertHasErrors('name');

    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($componente->errors()->first('name'));
});

test('los tres modos de cambio de contraseña sólo salen si la opción global está activa', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, null, ['is_admin' => true]);

    $this->actingAsTenant($usuario, $cuenta);

    config()->set('base-tenant.force_password_change.enabled', false);

    Livewire::test(EditAccount::class, ['account' => $cuenta])
        ->assertDontSee(__('base-tenant::accounts.force_password_inherit'));

    config()->set('base-tenant.force_password_change.enabled', true);

    Livewire::test(EditAccount::class, ['account' => $cuenta])
        ->assertSee(__('base-tenant::accounts.force_password_inherit'))
        ->assertSee(__('base-tenant::accounts.force_password_enabled'))
        ->assertSee(__('base-tenant::accounts.force_password_disabled'));
});

test('la ficha de cuenta sigue listando a sus miembros', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, null, ['is_admin' => true, 'name' => 'Jefe']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Miembro Visible']);

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(EditAccount::class, ['account' => $cuenta])
        ->assertSee(__('base-tenant::accounts.account_users'))
        ->assertSee('Miembro Visible');
});

test('la página de perfil sigue montando sus cinco piezas', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $this->get(route('base-tenant.profile'))
        ->assertOk()
        ->assertSeeLivewire('base-tenant.profile.update-profile-information-form')
        ->assertSeeLivewire('base-tenant.preferences')
        ->assertSeeLivewire('base-tenant.profile.update-password-form')
        ->assertSeeLivewire('base-tenant.two-factor-authentication')
        ->assertSeeLivewire('base-tenant.profile.delete-user-form');
});

/**
 * Los formularios de estas pantallas no llevan condicionales alrededor de sus
 * etiquetas `<form>`, y no deberían llevarlas nunca.
 */
test('la página de perfil abre y cierra sus formularios de forma equilibrada', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $html = $this->get(route('base-tenant.profile'))->assertOk()->getContent();

    preg_match_all('/<form\b|<\/form>/', $html, $encontrados);

    $profundidad = 0;
    $maxima = 0;

    foreach ($encontrados[0] as $etiqueta) {
        $profundidad += $etiqueta === '</form>' ? -1 : 1;
        $maxima = max($maxima, $profundidad);

        expect($profundidad)->toBeGreaterThanOrEqual(0, 'se cierra un <form> que nadie abrió');
    }

    expect($profundidad)->toBe(0, 'queda un <form> sin cerrar');
    expect($maxima)->toBe(1, 'hay un <form> dentro de otro');
});

/**
 * Usuario de una aplicación anfitriona que sí exige verificar el correo. Vive
 * sobre la misma tabla; sólo añade el contrato que activa el bloque de aviso.
 */
class UsuarioQueVerificaCorreo extends User implements Illuminate\Contracts\Auth\MustVerifyEmail
{
    use MustVerifyEmail;

    protected $table = 'users';
}
