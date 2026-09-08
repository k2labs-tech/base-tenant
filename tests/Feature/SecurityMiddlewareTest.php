<?php

declare(strict_types=1);

use Base\Tenant\BaseTenantServiceProvider;
use Base\Tenant\Facades\Security;
use Base\Tenant\Http\Middleware\EnforceIpAllowlist;
use Base\Tenant\Http\Middleware\EnforceSessionTimeout;
use Base\Tenant\Http\Middleware\EnforceTwoFactor;
use Base\Tenant\Http\Middleware\TrackUserSession;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Security\SecurityPolicySettings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Una petición con la ruta que se le diga, que es lo que los middleware miran
 * para decidir si están ante una página, ante la actualización de Livewire o
 * ante la petición que Livewire reconstruye a partir de la instantánea.
 */
function peticionConRuta(string $nombreRuta, string $ip = '203.0.113.7'): Request
{
    $peticion = Request::create('/dashboard', 'GET', server: ['REMOTE_ADDR' => $ip]);

    $peticion->setLaravelSession(app('session.store'));
    $peticion->setRouteResolver(fn () => Route::getRoutes()->getByName($nombreRuta));

    return $peticion;
}

function pasa(): Closure
{
    return fn (): Response => new Response('ok');
}

/**
 * Una petición de Livewire con la carga que manda el navegador: sin cambios de
 * propiedades y llamando sólo a `$refresh` es un `wire:poll`; con cualquier
 * otra llamada es algo que hizo la persona.
 */
function peticionLivewire(string $nombreRuta, array $calls, array $updates = []): Request
{
    $peticion = Request::create('/livewire/update', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.7',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LIVEWIRE' => '',
    ], content: json_encode([
        'components' => [['snapshot' => '{}', 'updates' => $updates, 'calls' => $calls]],
    ]));

    $peticion->setLaravelSession(app('session.store'));
    $peticion->setRouteResolver(fn () => Route::getRoutes()->getByName($nombreRuta));

    return $peticion;
}

/**
 * Cada acción de las pantallas del paquete es un POST al endpoint de
 * actualización de Livewire, que sólo vuelve a aplicar los middleware de su
 * lista persistente. Fuera de ella, una sesión revocada seguiría ejecutando
 * cada `wire:click` hasta que la persona cargase una página.
 */
test('los middleware de seguridad están en la lista persistente de Livewire', function () {
    $persistentes = Livewire::getPersistentMiddleware();

    foreach (BaseTenantServiceProvider::persistentMiddleware() as $middleware) {
        expect($persistentes)->toContain($middleware);
    }

    expect(BaseTenantServiceProvider::persistentMiddleware())->toContain(
        EnforceTwoFactor::class,
        EnforceIpAllowlist::class,
        EnforceSessionTimeout::class,
        TrackUserSession::class,
    );
});

/**
 * Livewire reconstruye la petición de la página original con su propia ruta
 * y ejecuta ahí el middleware persistente. Por eso `routeIs('base-tenant.profile')`
 * sí encaja en esa re-ejecución, y la persona fuera de plazo puede configurar
 * el doble factor en la única pantalla donde se hace.
 */
test('el doble factor vencido deja pasar la petición reconstruida de la pantalla de perfil', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['requireTwoFactor' => true, 'twoFactorGraceHours' => 0]);
    $usuario->forceFill(['two_factor_required_from' => now()->subDay()])->save();

    $this->actingAsTenant($usuario->fresh(), $cuenta);

    $middleware = new EnforceTwoFactor;

    expect($middleware->handle(peticionConRuta('base-tenant.profile'), pasa())->getStatusCode())->toBe(200)
        ->and($middleware->handle(peticionConRuta('base-tenant.dashboard'), pasa())->getStatusCode())->toBe(302);
});

/**
 * La petición de actualización de Livewire en sí no es el sitio donde
 * aplicar la regla: su ruta es `livewire.update`, así que ninguna vía de
 * escape podría coincidir, y la re-ejecución persistente ya la aplica contra
 * la ruta de la página. Aplicarla también aquí sería hacerlo dos veces y
 * encerrar al usuario en la pantalla de la que no puede salir.
 */
test('la petición de actualización de Livewire se deja a la re-ejecución persistente', function () {
    // Como lo haría un anfitrión: los middleware en el grupo `web`, que
    // comparten la ruta de actualización y las páginas.
    app('router')->pushMiddlewareToGroup('web', EnforceTwoFactor::class);
    app('router')->pushMiddlewareToGroup('web', EnforceIpAllowlist::class);

    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, [
        'requireTwoFactor' => true,
        'twoFactorGraceHours' => 0,
        'ipMode' => SecurityPolicySettings::IP_ENFORCE,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);
    $usuario->forceFill(['two_factor_required_from' => now()->subDay()])->save();

    $this->actingAsTenant($usuario->fresh(), $cuenta);

    $livewire = peticionConRuta('default-livewire.update');

    expect((new EnforceTwoFactor)->handle($livewire, pasa())->getStatusCode())->toBe(200)
        ->and((new EnforceIpAllowlist)->handle($livewire, pasa())->getStatusCode())->toBe(200)
        ->and((new EnforceIpAllowlist)->handle(peticionConRuta('base-tenant.dashboard'), pasa())->getStatusCode())->toBe(403);
})->throws(HttpException::class);

test('una sesión revocada se corta en la petición reconstruida de cualquier página', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    $this->actingAsTenant($usuario, $cuenta);

    $peticion = peticionConRuta('base-tenant.dashboard');

    UserSession::factory()->create([
        'user_id' => $usuario->getKey(),
        'session_id' => UserSession::fingerprint($peticion->session()->getId()),
        'revoked_at' => now(),
    ]);

    $respuesta = (new TrackUserSession)->handle($peticion, pasa());

    expect($respuesta->getStatusCode())->toBe(302)
        ->and(auth()->check())->toBeFalse();
});

/**
 * La re-ejecución de Livewire sólo recoge los middleware de la ruta de la
 * página y de sus grupos, nunca los globales del kernel. Un middleware
 * enganchado globalmente no aparecerá en esa re-ejecución, así que el único
 * sitio donde puede correr es la propia petición de actualización: saltarla
 * ahí sería dejar de aplicarlo del todo.
 */
test('enganchado globalmente, el middleware sí corre en la petición de actualización', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_ENFORCE,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);

    $this->actingAsTenant($usuario, $cuenta);

    // No está en el grupo `web` ni en la ruta: sólo pudo llegar aquí desde el kernel.
    (new EnforceIpAllowlist)->handle(peticionConRuta('default-livewire.update'), pasa());
})->throws(HttpException::class);

/**
 * En la re-ejecución, Livewire conserva un redirect y descarta cualquier otra
 * respuesta. Devolver un 403 en JSON dejaría pasar la acción: la persona
 * bloqueada sólo tendría que añadir `Accept: application/json` a su propia
 * petición. La respuesta se lanza, no se devuelve.
 */
test('la respuesta JSON de bloqueo se lanza en vez de devolverse', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_ENFORCE,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);

    $this->actingAsTenant($usuario, $cuenta);

    $peticion = peticionConRuta('base-tenant.dashboard');
    $peticion->headers->set('Accept', 'application/json');

    try {
        (new EnforceIpAllowlist)->handle($peticion, pasa());
    } catch (HttpResponseException $excepcion) {
        expect($excepcion->getResponse()->getStatusCode())->toBe(403);

        return;
    }

    $this->fail('La respuesta de bloqueo se devolvió en vez de lanzarse.');
});

/**
 * Con el middleware en la lista persistente, cada `wire:poll` lo re-ejecuta.
 * Si el tick contase como actividad, una pestaña abierta mantendría la sesión
 * viva para siempre y el tiempo de inactividad no saltaría nunca.
 */
test('un wire:poll no cuenta como actividad pero un clic sí', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['sessionTimeoutMinutes' => 15]);

    $this->actingAsTenant($usuario, $cuenta);

    $hace = time() - 300;
    session()->put('base-tenant.last_activity', $hace);

    (new EnforceSessionTimeout)->handle(
        peticionLivewire('base-tenant.dashboard', [['path' => '', 'method' => '$refresh', 'params' => []]]),
        pasa(),
    );

    expect(session()->get('base-tenant.last_activity'))->toBe($hace);

    (new EnforceSessionTimeout)->handle(
        peticionLivewire('base-tenant.dashboard', [['path' => '', 'method' => 'save', 'params' => []]]),
        pasa(),
    );

    expect(session()->get('base-tenant.last_activity'))->toBeGreaterThan($hace);
});

test('pasado el tiempo de inactividad, hasta un wire:poll cierra la sesión', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['sessionTimeoutMinutes' => 15]);

    $this->actingAsTenant($usuario, $cuenta);

    session()->put('base-tenant.last_activity', time() - 3600);

    $respuesta = (new EnforceSessionTimeout)->handle(
        peticionLivewire('base-tenant.dashboard', [['path' => '', 'method' => '$refresh', 'params' => []]]),
        pasa(),
    );

    expect($respuesta->getStatusCode())->toBe(302)
        ->and(auth()->check())->toBeFalse();
});

/**
 * El registro en modo aviso es lo que el administrador lee antes de pasar a
 * bloquear. Una fila por cada tick de cada pestaña abierta enterraría la
 * dirección que busca bajo miles de copias.
 */
test('el modo aviso registra una vez por sesión y dirección, no por petición', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_WARN,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);

    $this->actingAsTenant($usuario, $cuenta);

    foreach (range(1, 5) as $tick) {
        (new EnforceIpAllowlist)->handle(peticionConRuta('base-tenant.dashboard'), pasa());
    }

    $filas = fn (): int => ActivityLog::query()
        ->acrossAccounts()
        ->where('action', 'security.ip_would_be_blocked')
        ->count();

    expect($filas())->toBe(1);

    // Otra dirección sí es información nueva.
    (new EnforceIpAllowlist)->handle(peticionConRuta('base-tenant.dashboard', '203.0.113.8'), pasa());

    expect($filas())->toBe(2);
});
