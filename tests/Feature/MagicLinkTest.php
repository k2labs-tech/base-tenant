<?php

declare(strict_types=1);

use Base\Tenant\Facades\MagicLink as MagicLinkFacade;
use Base\Tenant\Livewire\Auth\RequestMagicLink;
use Base\Tenant\Models\MagicLink;
use Base\Tenant\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('base-tenant:magic-link:ip:127.0.0.1');
});

/**
 * Crea un enlace de verdad y devuelve el token en claro, que es lo único que
 * el usuario llega a ver.
 */
function enlaceParaUsuario($usuario, array $atributos = []): string
{
    $token = Str::random(48);

    MagicLink::create([
        'email' => $usuario->email,
        'user_id' => $usuario->getKey(),
        'token' => MagicLink::fingerprint($token),
        'expires_at' => now()->addMinutes(15),
        ...$atributos,
    ]);

    return $token;
}

// ---------------------------------------------------------------------
// El token
// ---------------------------------------------------------------------

/**
 * Quien tiene el token en claro puede convertirse en el usuario. Guardarlo sin
 * hashear convierte una copia de seguridad filtrada en un juego de accesos.
 */
test('el token se guarda hasheado, nunca en claro', function () {
    Notification::fake();

    $usuario = $this->createUser();

    MagicLinkFacade::request($usuario->email);

    $fila = MagicLink::query()->firstOrFail();

    expect($fila->token)->toHaveLength(64)
        ->and(MagicLink::query()->where('token', 'like', '%'.$fila->token.'%')->count())->toBe(1);

    // Y el token que viaja en el correo no está en la base de datos.
    Notification::assertSentTo($usuario, MagicLinkNotification::class);
});

// ---------------------------------------------------------------------
// Un solo uso
// ---------------------------------------------------------------------

test('un enlace sirve una vez', function () {
    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario);

    expect(MagicLinkFacade::consume($token)?->getKey())->toBe($usuario->getKey())
        ->and(MagicLinkFacade::consume($token))->toBeNull();
});

test('un enlace caducado no sirve', function () {
    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario, ['expires_at' => now()->subMinute()]);

    expect(MagicLinkFacade::consume($token))->toBeNull();
});

test('un enlace ya gastado no sirve', function () {
    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario, ['consumed_at' => now()]);

    expect(MagicLinkFacade::consume($token))->toBeNull();
});

test('un token inventado no sirve', function () {
    $this->createUser();

    expect(MagicLinkFacade::consume(Str::random(48)))->toBeNull();
});

/**
 * Dos peticiones con el mismo token en el mismo instante: sólo una puede
 * ganar. Leer y luego escribir dejaría pasar a las dos.
 */
test('gastar el enlace es atómico', function () {
    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario);

    $primero = MagicLinkFacade::consume($token);
    $segundo = MagicLinkFacade::consume($token);

    expect([$primero !== null, $segundo !== null])->toBe([true, false]);
});

test('usar un enlace queda auditado', function () {
    $usuario = $this->createUser();

    MagicLinkFacade::consume(enlaceParaUsuario($usuario));

    $this->assertDatabaseHas('activity_log', ['action' => 'auth.magic_link_used']);
});

// ---------------------------------------------------------------------
// LA guarda: el segundo factor no se salta
// ---------------------------------------------------------------------

/**
 * Un magic link demuestra que tienes el buzón. No demuestra el segundo factor,
 * que es justo lo que existe para cubrir el caso de que alguien llegue al
 * buzón. Entrar directo aquí convertiría toda cuenta con 2FA en una cuenta
 * cuyo 2FA se salta desde el correo.
 *
 * Quitar la comprobación de `hasTwoFactorEnabled()` del controlador tiene que
 * poner este test en rojo.
 */
test('un enlace NO salta el doble factor', function () {
    $usuario = $this->createUser();
    $usuario->forceFill([
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $token = enlaceParaUsuario($usuario->fresh());

    $this->get(route('base-tenant.magic-link.consume', ['token' => $token]))
        ->assertRedirect(route('base-tenant.two-factor.challenge'));

    expect(auth()->check())->toBeFalse();
    expect(session('2fa.user_id'))->toBe($usuario->getKey());
});

test('sin doble factor el enlace entra directo', function () {
    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario);

    $this->get(route('base-tenant.magic-link.consume', ['token' => $token]))
        ->assertRedirect(route('base-tenant.dashboard'));

    expect(auth()->id())->toBe($usuario->getKey());
});

test('un enlace inválido devuelve al login con el motivo', function () {
    $this->get(route('base-tenant.magic-link.consume', ['token' => Str::random(48)]))
        ->assertRedirect(route('base-tenant.login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

// ---------------------------------------------------------------------
// No revelar quién es cliente
// ---------------------------------------------------------------------

/**
 * Responder distinto según exista o no la dirección convierte el formulario en
 * una forma de preguntarle al producto «¿es esta persona cliente vuestro?».
 */
test('pedir un enlace para una dirección desconocida no crea nada ni delata', function () {
    Notification::fake();

    MagicLinkFacade::request('nadie@ninguna-parte.test');

    expect(MagicLink::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('la pantalla dice lo mismo exista o no la dirección', function () {
    Notification::fake();

    $usuario = $this->createUser();

    $conocida = Livewire::test(RequestMagicLink::class)
        ->set('email', $usuario->email)
        ->call('send');

    RateLimiter::clear('base-tenant:magic-link:ip:127.0.0.1');

    $desconocida = Livewire::test(RequestMagicLink::class)
        ->set('email', 'nadie@ninguna-parte.test')
        ->call('send');

    $conocida->assertSet('sent', true)->assertHasNoErrors();
    $desconocida->assertSet('sent', true)->assertHasNoErrors();
});

// ---------------------------------------------------------------------
// Límite de frecuencia
// ---------------------------------------------------------------------

test('pedir demasiados enlaces se corta', function () {
    Notification::fake();

    config(['base-tenant.passwordless.magic_links.max_per_hour' => 2]);

    $usuario = $this->createUser();

    foreach (range(1, 2) as $intento) {
        MagicLinkFacade::recordRequest($usuario->email, request());
    }

    expect(MagicLinkFacade::tooManyRequests($usuario->email, request()))->toBeTrue();
});

test('la pantalla avisa al llegar al límite en vez de callarse', function () {
    Notification::fake();

    config(['base-tenant.passwordless.magic_links.max_per_hour' => 1]);

    $usuario = $this->createUser();

    Livewire::test(RequestMagicLink::class)
        ->set('email', $usuario->email)
        ->call('send')
        ->assertSet('sent', true);

    Livewire::test(RequestMagicLink::class)
        ->set('email', $usuario->email)
        ->call('send')
        ->assertHasErrors('email');
});

// ---------------------------------------------------------------------
// El interruptor
// ---------------------------------------------------------------------

test('con los magic links apagados la ruta no existe', function () {
    config(['base-tenant.passwordless.magic_links.enabled' => false]);

    $usuario = $this->createUser();
    $token = enlaceParaUsuario($usuario);

    $this->get(route('base-tenant.magic-link.consume', ['token' => $token]))
        ->assertNotFound();
});

// ---------------------------------------------------------------------
// Poda
// ---------------------------------------------------------------------

test('la poda borra los enlaces viejos y respeta los recientes', function () {
    $usuario = $this->createUser();

    MagicLink::factory()->create(['user_id' => $usuario->getKey(), 'created_at' => now()->subDays(30)]);
    MagicLink::factory()->create(['user_id' => $usuario->getKey(), 'created_at' => now()->subHour()]);

    MagicLinkFacade::prune(7);

    expect(MagicLink::query()->count())->toBe(1);
});
