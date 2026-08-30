<?php

declare(strict_types=1);

use Base\Tenant\Livewire\Auth\ConfirmPassword;
use Base\Tenant\Livewire\Auth\ForcePasswordChange;
use Base\Tenant\Livewire\Auth\ForgotPassword;
use Base\Tenant\Livewire\Auth\Login;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\Auth\ResetPassword;
use Base\Tenant\Livewire\Auth\VerifyEmail;
use Base\Tenant\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Las siete pantallas de autenticación son las únicas del paquete a las que se
 * llega sin haber entrado, así que un fallo aquí no deja a nadie dentro para
 * contarlo. Lo que se protege:
 *
 * - Que el mensaje de credenciales incorrectas siga apareciendo. Sale de
 *   `LoginForm::authenticate()` sobre la clave `form.email`, no sobre `email`:
 *   un campo de Flux ata su error al `wire:model`, así que basta escribir
 *   `wire:model="email"` en vez de `wire:model="form.email"` para que el aviso
 *   más importante de la aplicación deje de verse sin romper nada más.
 * - Que cada botón de envío diga que está enviando. Un login que no responde
 *   invita a pulsar otra vez, y un login enviado dos veces es un ticket.
 * - Que los avisos de sesión (`session('status')`) sigan llegando.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón de invitado revienta buscando un manifiesto que aquí no existe.
    $this->withoutVite();

    $this->syncPermissions();
});

test('el error de credenciales sigue viéndose en el formulario de acceso', function () {
    $this->createUser(null, null, [
        'email' => 'titular@ejemplo.test',
        'password' => Hash::make('contrasena-correcta'),
    ]);

    $componente = Livewire::test(Login::class)
        ->set('form.email', 'titular@ejemplo.test')
        ->set('form.password', 'no-es-esta')
        ->call('login')
        ->assertHasErrors('form.email');

    // El aviso vive bajo `form.email`. Si el campo se ata a `email` a secas,
    // el error existe pero no se pinta: esto es lo que lo detecta.
    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee(trans('auth.failed'));
});

test('el aviso de demasiados intentos también se ve', function () {
    $this->createUser(null, null, [
        'email' => 'titular@ejemplo.test',
        'password' => Hash::make('contrasena-correcta'),
    ]);

    $componente = Livewire::test(Login::class)
        ->set('form.email', 'titular@ejemplo.test')
        ->set('form.password', 'no-es-esta');

    // El limitador salta al sexto intento.
    foreach (range(1, 6) as $intento) {
        $componente->call('login');
    }

    $componente->assertHasErrors('form.email');
    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($componente->errors()->first('form.email'));
});

/**
 * Monta la pantalla pedida con lo que necesite para existir.
 *
 * Vive aquí y no en los datos porque los closures de un dataset se evalúan
 * fuera del ámbito del caso de prueba, donde los ayudantes protegidos de
 * `TestCase` no se pueden llamar.
 */
function montarPantalla(object $prueba, string $pantalla): Testable
{
    return match ($pantalla) {
        'login' => Livewire::test(Login::class),
        'register' => Livewire::test(Register::class),
        'forgot' => Livewire::test(ForgotPassword::class),
        'reset' => Livewire::test(ResetPassword::class, ['token' => 'token-inventado']),
        'confirm' => (function () use ($prueba) {
            $prueba->actingAs(User::factory()->create(['password' => Hash::make('contrasena-correcta')]));

            return Livewire::test(ConfirmPassword::class);
        })(),
        'force' => (function () use ($prueba) {
            $prueba->actingAs(User::factory()->create([
                'password' => Hash::make('contrasena-correcta'),
                'must_change_password' => true,
            ]));

            return Livewire::test(ForcePasswordChange::class);
        })(),
        // `mount` manda aquí el correo de verificación, así que esta pantalla
        // sólo se puede montar desde que el generador de enlaces apunta a la
        // ruta del paquete.
        'verify' => (function () use ($prueba) {
            $prueba->actingAs(User::factory()->create(['email_verified_at' => null]));

            return Livewire::test(VerifyEmail::class);
        })(),
    };
}

/**
 * El barrido: cada caso es un campo que hoy enseña su error y que una
 * reescritura puede dejar mudo.
 */
test('cada error de validación llega a la pantalla', function (string $pantalla, array $valores, string $metodo, string $campo) {
    $componente = montarPantalla($this, $pantalla);

    foreach ($valores as $propiedad => $valor) {
        $componente->set($propiedad, $valor);
    }

    $componente->call($metodo)->assertHasErrors($campo);

    $mensaje = $componente->errors()->first($campo);

    expect($mensaje)->not->toBe('');

    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($mensaje);
})->with([
    'acceso sin correo' => ['login', ['form.email' => '', 'form.password' => 'algo'], 'login', 'form.email'],
    'registro sin nombre' => ['register', ['name' => '', 'companyName' => 'Acme', 'email' => 'nuevo@ejemplo.test', 'password' => 'contrasena-larga', 'password_confirmation' => 'contrasena-larga'], 'register', 'name'],
    'registro sin empresa' => ['register', ['name' => 'Alguien', 'companyName' => '', 'email' => 'nuevo@ejemplo.test', 'password' => 'contrasena-larga', 'password_confirmation' => 'contrasena-larga'], 'register', 'companyName'],
    'registro sin confirmar la contraseña' => ['register', ['name' => 'Alguien', 'companyName' => 'Acme', 'email' => 'nuevo@ejemplo.test', 'password' => 'contrasena-larga', 'password_confirmation' => 'otra-cosa'], 'register', 'password'],
    'recuperación con correo inválido' => ['forgot', ['email' => 'esto-no-es-un-correo'], 'sendPasswordResetLink', 'email'],
    'restablecer con token inválido' => ['reset', ['email' => 'nadie@ejemplo.test', 'password' => 'contrasena-larga', 'password_confirmation' => 'contrasena-larga'], 'resetPassword', 'email'],
    'restablecer sin confirmar' => ['reset', ['email' => 'nadie@ejemplo.test', 'password' => 'contrasena-larga', 'password_confirmation' => 'otra-cosa'], 'resetPassword', 'password'],
    'confirmar con la contraseña equivocada' => ['confirm', ['password' => 'no-es-esta'], 'confirmPassword', 'password'],
    'cambio obligatorio con la contraseña actual equivocada' => ['force', ['current_password' => 'no-es-esta', 'password' => 'contrasena-nueva-larga', 'password_confirmation' => 'contrasena-nueva-larga'], 'updatePassword', 'current_password'],
    'cambio obligatorio repitiendo la contraseña de siempre' => ['force', ['current_password' => 'contrasena-correcta', 'password' => 'contrasena-correcta', 'password_confirmation' => 'contrasena-correcta'], 'updatePassword', 'password'],
]);

/**
 * Un botón que no dice nada mientras el servidor piensa invita a pulsarlo otra
 * vez. Cada pantalla tiene que declarar su estado de espera apuntando al método
 * que envía, no a cualquiera.
 */
test('cada pantalla avisa mientras envía', function (string $pantalla, string $metodo) {
    $html = montarPantalla($this, $pantalla)->html();

    expect($html)->toContain('wire:loading');
    expect($html)->toContain('wire:target="'.$metodo.'"');
})->with([
    'acceso' => ['login', 'login'],
    'registro' => ['register', 'register'],
    'recuperación' => ['forgot', 'sendPasswordResetLink'],
    'restablecer' => ['reset', 'resetPassword'],
    'confirmar' => ['confirm', 'confirmPassword'],
    'verificar correo' => ['verify', 'sendVerification'],
    'cambio obligatorio' => ['force', 'updatePassword'],
]);

test('el aviso de sesión sigue apareciendo al pedir el enlace', function () {
    session()->flash('status', 'Te hemos enviado el enlace.');

    Livewire::test(ForgotPassword::class)
        ->assertSee('Te hemos enviado el enlace.');
});

test('el acceso muestra el aviso de sesión que deja el restablecimiento', function () {
    session()->flash('status', 'Tu contraseña se ha restablecido.');

    Livewire::test(Login::class)
        ->assertSee('Tu contraseña se ha restablecido.');
});

test('verificar correo avisa de que el enlace se ha reenviado', function () {
    session()->flash('status', 'verification-link-sent');

    $componente = montarPantalla($this, 'verify');

    $componente->assertSee(__('A new verification link has been sent to the email address you provided during registration.'));
});

test('el cambio obligatorio sigue explicando por qué se pide', function () {
    $usuario = $this->createUser(null, null, ['must_change_password' => true]);

    $this->actingAs($usuario);

    Livewire::test(ForcePasswordChange::class)
        ->assertSee(__('base-tenant::auth.password_change_required'))
        ->assertSee(__('base-tenant::auth.account_created_by_admin'))
        ->assertSee(__('base-tenant::auth.password_requirements'))
        ->assertSeeHtml('wire:click="logout"');
});

test('el acceso conserva el recuerdo y el enlace de contraseña olvidada', function () {
    $componente = Livewire::test(Login::class);

    $componente->assertSeeHtml('wire:model="form.remember"');
    $componente->assertSee(__('Forgot your password?'));
    $componente->assertSeeHtml(route('base-tenant.password.request'));
});

test('el registro conserva el enlace a acceder', function () {
    Livewire::test(Register::class)
        ->assertSee(__('Already registered?'))
        ->assertSeeHtml(route('base-tenant.login'));
});

test('verificar correo conserva la salida', function () {
    montarPantalla($this, 'verify')->assertSeeHtml('wire:click="logout"');
});

/**
 * Las siete pantallas caben en la tarjeta centrada del armazón de invitado: si
 * alguna se llevase el patrón de dos columnas del resto del rediseño, se
 * partiría en una columna vacía y otra apretada.
 */
test('ninguna pantalla de autenticación usa el patrón de dos columnas', function () {
    $raiz = dirname(__DIR__, 2).'/resources/views/livewire/auth';

    $culpables = [];

    foreach (glob($raiz.'/*.blade.php') ?: [] as $ruta) {
        if (str_contains((string) file_get_contents($ruta), 'md:grid-cols-3')) {
            $culpables[] = basename($ruta);
        }
    }

    expect($culpables)->toBe([]);
});

/**
 * La notificación de verificación de Laravel firma su enlace contra la ruta
 * `verification.verify`. El paquete registra la suya como
 * `base-tenant.verification.verify` y nunca reasignaba el generador, así que
 * cualquier usuario sin verificar que llegase a la pantalla se comía un 500 en
 * `mount`, que es justo donde se manda el correo.
 */
test('un usuario sin verificar puede abrir la pantalla de verificación', function () {
    $usuario = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($usuario);

    Livewire::test(VerifyEmail::class)->assertOk();
});

test('el enlace que genera la notificación lo acepta la ruta del paquete', function () {
    $usuario = User::factory()->create(['email_verified_at' => null]);

    $mensaje = (new Illuminate\Auth\Notifications\VerifyEmail)->toMail($usuario);

    $url = $mensaje->actionUrl;

    expect($url)->toContain('verify-email/'.$usuario->getKey().'/'.sha1($usuario->getEmailForVerification()));
    expect($url)->toContain('signature=');

    // La firma, la caducidad y el hash del correo tienen que ser los que valida
    // la ruta: si no cuadran, el enlace deja de dar un 500 para dar un 403, que
    // no es ninguna mejora.
    $this->actingAs($usuario)->get($url)->assertRedirect();

    expect($usuario->refresh()->email_verified_at)->not->toBeNull();
});

test('un enlace de verificación sin firma no vale', function () {
    $usuario = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($usuario)
        ->get(route('base-tenant.verification.verify', [
            'id' => $usuario->getKey(),
            'hash' => sha1($usuario->getEmailForVerification()),
        ]))
        ->assertForbidden();

    expect($usuario->refresh()->email_verified_at)->toBeNull();
});
