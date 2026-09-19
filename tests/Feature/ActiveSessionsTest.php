<?php

declare(strict_types=1);

use Base\Tenant\Facades\Sessions;
use Base\Tenant\Gdpr\Exporters\SessionExporter;
use Base\Tenant\Livewire\Profile\ActiveSessions;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Sessions\DeviceParser;
use Illuminate\Http\Request;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Una petición con sesión de verdad, que es lo que el middleware necesita.
 */
function peticionConSesion(string $ip = '203.0.113.7', ?string $agente = null): Request
{
    $peticion = Request::create('/dashboard', 'GET', server: [
        'REMOTE_ADDR' => $ip,
        'HTTP_USER_AGENT' => $agente ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36',
    ]);

    $peticion->setLaravelSession(app('session.store'));

    return $peticion;
}

// ---------------------------------------------------------------------
// El identificador de sesión
// ---------------------------------------------------------------------

/**
 * El id de sesión es una credencial al portador: quien lo tiene ES la sesión.
 * Guardarlo en claro convierte una copia de seguridad filtrada en un juego de
 * sesiones vivas.
 */
test('el identificador de sesión se guarda hasheado, nunca en claro', function () {
    $usuario = $this->createUser();
    $peticion = peticionConSesion();
    $idReal = $peticion->session()->getId();

    Sessions::touch($peticion, $usuario);

    $fila = UserSession::query()->firstOrFail();

    expect($fila->session_id)->not->toBe($idReal)
        ->and($fila->session_id)->toBe(hash('sha256', $idReal))
        ->and($fila->session_id)->toHaveLength(64);
});

// ---------------------------------------------------------------------
// Registro
// ---------------------------------------------------------------------

test('la primera petición crea la fila con dispositivo y navegador', function () {
    $usuario = $this->createUser();

    Sessions::touch(peticionConSesion(), $usuario);

    $fila = UserSession::query()->firstOrFail();

    expect($fila->user_id)->toBe($usuario->getKey())
        ->and($fila->ip_address)->toBe('203.0.113.7')
        ->and($fila->browser)->toBe('Chrome')
        ->and($fila->platform)->toBe('macOS')
        ->and($fila->device)->toBe('desktop');
});

test('las peticiones siguientes actualizan la misma fila, no crean otra', function () {
    $usuario = $this->createUser();
    $peticion = peticionConSesion();

    Sessions::touch($peticion, $usuario);
    Sessions::touch($peticion, $usuario);
    Sessions::touch($peticion, $usuario);

    expect(UserSession::query()->count())->toBe(1);
});

/**
 * Quien revocó decidió que esa sesión se acabó. Sin la salida temprana, cada
 * petición de una sesión revocada le seguiría refrescando la última actividad
 * y la IP, y quien mirase los datos en crudo vería una sesión recién activa
 * que en realidad está cerrada.
 */
test('una sesión revocada deja de actualizarse', function () {
    $usuario = $this->createUser();
    $peticion = peticionConSesion();

    Sessions::touch($peticion, $usuario);
    $fila = UserSession::query()->firstOrFail();
    Sessions::revoke($fila);

    $actividadAlRevocar = $fila->fresh()->last_active_at;

    $this->travel(30)->minutes();
    Sessions::touch(peticionConSesion('198.51.100.9'), $usuario);

    $despues = $fila->fresh();

    expect($despues->revoked_at)->not->toBeNull()
        ->and($despues->last_active_at->timestamp)->toBe($actividadAlRevocar->timestamp)
        ->and($despues->ip_address)->toBe('203.0.113.7');
});

// ---------------------------------------------------------------------
// Revocación
// ---------------------------------------------------------------------

test('una sesión revocada se detecta en la siguiente petición', function () {
    $usuario = $this->createUser();
    $peticion = peticionConSesion();

    Sessions::touch($peticion, $usuario);
    expect(Sessions::isRevoked($peticion))->toBeFalse();

    Sessions::revoke(UserSession::query()->firstOrFail());

    expect(Sessions::isRevoked($peticion))->toBeTrue();
});

test('las sesiones revocadas desaparecen del listado', function () {
    $usuario = $this->createUser();

    UserSession::factory()->count(2)->create(['user_id' => $usuario->getKey()]);
    UserSession::factory()->revoked()->create(['user_id' => $usuario->getKey()]);

    expect(Sessions::forUser($usuario))->toHaveCount(2);
});

/**
 * Cerrarle la sesión a alguien en la pantalla que está usando para asegurar su
 * cuenta es como se queda a medias.
 */
test('cerrar las demás conserva la actual', function () {
    $usuario = $this->createUser();
    $peticion = peticionConSesion();

    Sessions::touch($peticion, $usuario);
    $actual = UserSession::query()->firstOrFail();

    UserSession::factory()->count(3)->create(['user_id' => $usuario->getKey()]);

    $cerradas = Sessions::revokeOthers($usuario, $peticion->session()->getId());

    expect($cerradas)->toBe(3)
        ->and($actual->fresh()->revoked_at)->toBeNull()
        ->and(Sessions::forUser($usuario))->toHaveCount(1);
});

test('cerrar todas no conserva ninguna', function () {
    $usuario = $this->createUser();
    UserSession::factory()->count(3)->create(['user_id' => $usuario->getKey()]);

    expect(Sessions::revokeAll($usuario))->toBe(3)
        ->and(Sessions::forUser($usuario))->toHaveCount(0);
});

test('las sesiones de un usuario no se mezclan con las de otro', function () {
    $mio = $this->createUser();
    $ajeno = $this->createUser();

    UserSession::factory()->count(2)->create(['user_id' => $mio->getKey()]);
    UserSession::factory()->count(3)->create(['user_id' => $ajeno->getKey()]);

    Sessions::revokeAll($mio);

    expect(Sessions::forUser($ajeno))->toHaveCount(3);
});

test('cada revocación queda auditada', function () {
    $usuario = $this->createUser();
    $sesion = UserSession::factory()->create(['user_id' => $usuario->getKey()]);

    Sessions::revoke($sesion);

    $this->assertDatabaseHas('activity_log', ['action' => 'session.revoked']);
});

// ---------------------------------------------------------------------
// Lectura del agente
// ---------------------------------------------------------------------

/**
 * Edge y Opera dicen ser Chrome, y Chrome dice ser Safari. Comprobar primero a
 * los impostores es todo el truco.
 */
test('el navegador se reconoce pese a que todos se hacen pasar por otro', function (string $agente, ?string $esperado) {
    expect(DeviceParser::parse($agente)['browser'])->toBe($esperado);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0', 'Edge'],
    ['Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36 OPR/106.0', 'Opera'],
    ['Mozilla/5.0 (Windows NT 10.0) Gecko/20100101 Firefox/121.0', 'Firefox'],
    ['Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120.0 Safari/537.36', 'Chrome'],
    ['Mozilla/5.0 (Macintosh) AppleWebKit/605.1 Version/17.0 Safari/605.1', 'Safari'],
    ['algo-que-no-es-un-navegador', null],
]);

test('el tipo de dispositivo se distingue', function (string $agente, string $esperado) {
    expect(DeviceParser::parse($agente)['device'])->toBe($esperado);
})->with([
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile/15E148', 'mobile'],
    ['Mozilla/5.0 (iPad; CPU OS 17_0) Mobile/15E148', 'tablet'],
    ['Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', 'desktop'],
]);

test('un agente vacío no revienta', function () {
    expect(DeviceParser::parse(null))
        ->toBe(['device' => null, 'browser' => null, 'platform' => null]);
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

test('cada quien ve sus propias sesiones', function () {
    $usuario = $this->createUser();
    UserSession::factory()->count(2)->create(['user_id' => $usuario->getKey()]);

    $this->actingAsTenant($usuario);

    Livewire::test(ActiveSessions::class)
        ->assertOk()
        ->assertViewHas('sessions', fn ($sesiones): bool => $sesiones->count() === 2);
});

test('mirar las sesiones de otro exige permiso sobre usuarios', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'pelado');
    $curioso = $this->createUser($cuenta, 'pelado');
    $otro = $this->createUser($cuenta);

    $this->actingAsTenant($curioso, $cuenta);

    Livewire::test(ActiveSessions::class, ['user' => $otro])->assertForbidden();
});

test('un administrador de la cuenta puede cerrar las sesiones de un miembro', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'jefe', ['users.view', 'users.update']);
    $jefe = $this->createUser($cuenta, 'jefe');
    $miembro = $this->createUser($cuenta);

    UserSession::factory()->count(2)->create(['user_id' => $miembro->getKey()]);

    $this->actingAsTenant($jefe, $cuenta);

    Livewire::test(ActiveSessions::class, ['user' => $miembro])
        ->call('revokeOthers');

    expect(Sessions::forUser($miembro))->toHaveCount(0);
});

/**
 * El estado público de un componente Livewire viaja con la petición. El
 * identificador del usuario mirado está bloqueado: si el navegador pudiera
 * reescribirlo, cualquiera apuntaría esta pantalla a cualquier usuario de la
 * instalación.
 */
test('el usuario mirado no se puede reescribir desde el navegador', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'pelado');
    $curioso = $this->createUser($cuenta, 'pelado');
    $victima = $this->createUser($cuenta);

    $sesion = UserSession::factory()->create(['user_id' => $victima->getKey()]);

    $this->actingAsTenant($curioso, $cuenta);

    expect(fn () => Livewire::test(ActiveSessions::class)
        ->set('userId', $victima->getKey())
        ->call('revoke', $sesion->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect($sesion->fresh()->revoked_at)->toBeNull();
});

/**
 * El permiso `users.update` vale dentro de la cuenta en contexto y de ninguna
 * otra. Sin esta comprobación, un administrador de una cuenta podría ver y
 * cerrar las sesiones de los usuarios de todas las demás.
 */
test('un administrador no ve ni cierra las sesiones de usuarios de otra cuenta', function () {
    $this->syncPermissions();

    $suya = $this->createAccount();
    $this->createRole($suya, 'jefe', ['users.view', 'users.update']);
    $jefe = $this->createUser($suya, 'jefe');

    $ajena = $this->createAccount();
    $forastero = $this->createUser($ajena);
    $sesion = UserSession::factory()->create(['user_id' => $forastero->getKey()]);

    $this->actingAsTenant($jefe, $suya);

    Livewire::test(ActiveSessions::class, ['user' => $forastero])->assertForbidden();

    expect($sesion->fresh()->revoked_at)->toBeNull();
});

// ---------------------------------------------------------------------
// Poda
// ---------------------------------------------------------------------

test('el comando de poda borra lo viejo y respeta lo reciente', function () {
    $usuario = $this->createUser();

    UserSession::factory()->create([
        'user_id' => $usuario->getKey(),
        'last_active_at' => now()->subDays(90),
    ]);
    UserSession::factory()->create([
        'user_id' => $usuario->getKey(),
        'last_active_at' => now()->subDay(),
    ]);

    $this->artisan('k2labs-base:prune-sessions --days=30')->assertSuccessful();

    expect(UserSession::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------
// GDPR
// ---------------------------------------------------------------------

/**
 * Una sesión guarda dirección, dispositivo y a qué horas trabaja alguien. Es
 * dato personal y tiene que salir en una exportación -- pero el identificador
 * de sesión no, ni siquiera hasheado: identifica una credencial y el fichero
 * viaja.
 */
test('la exportación incluye las sesiones y nunca su identificador', function () {
    $usuario = $this->createUser();
    UserSession::factory()->create(['user_id' => $usuario->getKey()]);

    $exportado = (new SessionExporter)->export($usuario);

    expect($exportado)->toHaveCount(1)
        ->and($exportado[0])->toHaveKeys(['ip_address', 'device', 'browser', 'last_active_at'])
        ->and($exportado[0])->not->toHaveKey('session_id');
});
