<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\DomainNotAllowedException;
use Base\Tenant\Exceptions\InvitationException;
use Base\Tenant\Facades\Security;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\SecurityPolicyManager as SecurityPolicyManagerComponent;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Security\SecurityPolicySettings;
use Base\Tenant\Services\InvitationService;
use Base\Tenant\Support\IpRange;
use Illuminate\Auth\Events\Login;
use Livewire\Livewire;

// ---------------------------------------------------------------------
// Los valores por defecto
// ---------------------------------------------------------------------

/**
 * Una regla que llegara encendida con una actualización dejaría fuera a gente
 * de una cuenta que no pidió ningún cambio. Todos los defectos son permisivos,
 * y esto lo vigila.
 */
test('la política por defecto no restringe nada', function () {
    $cuenta = $this->createAccount();

    $politica = Security::for($cuenta);

    expect($politica->requireTwoFactor)->toBeFalse()
        ->and($politica->allowedEmailDomains)->toBe([])
        ->and($politica->ipMode)->toBe(SecurityPolicySettings::IP_OFF)
        ->and($politica->ipAllowlist)->toBe([])
        ->and($politica->sessionTimeoutMinutes)->toBe(0);
});

test('sin cuenta en contexto la política es la permisiva', function () {
    expect(Security::allowsIp('203.0.113.9'))->toBeTrue()
        ->and(Security::allowsEmail('quien@sea.com'))->toBeTrue()
        ->and(Security::requiresTwoFactor())->toBeFalse();
});

// ---------------------------------------------------------------------
// Dominios de email
// ---------------------------------------------------------------------

test('con dominios permitidos sólo entran esas direcciones', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, ['allowedEmailDomains' => ['micliente.com']]);

    expect(Security::allowsEmail('ada@micliente.com', $cuenta))->toBeTrue()
        ->and(Security::allowsEmail('ada@gmail.com', $cuenta))->toBeFalse()
        ->and(Security::allowsEmail('sinarroba', $cuenta))->toBeFalse();
});

test('la lista vacía no restringe', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, ['allowedEmailDomains' => []]);

    expect(Security::allowsEmail('cualquiera@donde.sea', $cuenta))->toBeTrue();
});

test('los dominios se normalizan al escribirlos como venga', function () {
    expect(Security::normalizeDomains([' @MiCliente.COM ', 'ada@otro.com', 'basura', '']))
        ->toBe(['micliente.com', 'otro.com']);
});

/**
 * La regla se aplica en el servicio y no en la pantalla: una invitación puede
 * salir de un comando o de un job, y el sentido de la regla es que nadie entre
 * en la cuenta por el lado.
 */
test('invitar a un dominio no permitido se rechaza en el servicio', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    Security::update($cuenta, ['allowedEmailDomains' => ['micliente.com']]);

    expect(fn () => InvitationService::send('ada@gmail.com', $cuenta->getKey(), $rol->id, $invitador))
        ->toThrow(DomainNotAllowedException::class);

    $this->assertDatabaseMissing('user_invites', ['email' => 'ada@gmail.com']);
});

test('invitar a un dominio permitido sigue funcionando', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    Security::update($cuenta, ['allowedEmailDomains' => ['micliente.com']]);

    InvitationService::send('ada@micliente.com', $cuenta->getKey(), $rol->id, $invitador);

    $this->assertDatabaseHas('user_invites', ['email' => 'ada@micliente.com']);
});

/**
 * La regla se puede endurecer después de que la invitación haya salido. La
 * invitación pendiente a una dirección que ya no se admite no se puede
 * refrescar y volver a enviar por el lado.
 */
test('reenviar una invitación a un dominio que ya no se admite se rechaza', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('bob@gmail.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    Security::update($cuenta, ['allowedEmailDomains' => ['micliente.com']]);

    expect(fn () => InvitationService::resend($invitacion))
        ->toThrow(DomainNotAllowedException::class);
});

test('aceptar una invitación a un dominio que ya no se admite se rechaza', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('bob@gmail.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    Security::update($cuenta, ['allowedEmailDomains' => ['micliente.com']]);

    $bob = User::factory()->create(['account_id' => null, 'email' => 'bob@gmail.com']);

    expect(fn () => InvitationService::accept($invitacion, $bob))
        ->toThrow(DomainNotAllowedException::class);

    expect($bob->fresh()->belongsToAccount($cuenta))->toBeFalse();
});

/**
 * La invitación es para la dirección a la que se envió. Quien esté conectado
 * cuando se abre el enlace no es necesariamente esa persona, y engancharla
 * metería en la cuenta a cualquiera con el enlace bajo una dirección que el
 * administrador nunca aprobó -- lo que deja sin efecto la regla de dominios.
 */
test('una invitación no la puede aceptar alguien con otra dirección', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('alice@micliente.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    $bob = User::factory()->create(['account_id' => null, 'email' => 'bob@gmail.com']);

    expect(fn () => InvitationService::accept($invitacion, $bob))
        ->toThrow(InvitationException::class);

    expect($bob->fresh()->belongsToAccount($cuenta))->toBeFalse()
        ->and($invitacion->fresh()->accepted_at)->toBeNull();
});

test('abrir el enlace de otra persona con sesión iniciada no engancha y explica por qué', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('alice@micliente.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    $bob = $this->createUser(null, null, ['email' => 'bob@gmail.com']);

    $this->actingAsTenant($bob);

    $this->get(route('base-tenant.invitations.accept', ['token' => $invitacion->token]))
        ->assertRedirect(route('base-tenant.dashboard'))
        ->assertSessionHas('error', __('base-tenant::invitations.for_another_address'));

    expect($bob->fresh()->belongsToAccount($cuenta))->toBeFalse();
});

/**
 * Quien abre el enlace sin sesión va al login, y al entrar la invitación se
 * completa sola, sea cual sea la forma de entrar: todas disparan el mismo
 * evento.
 */
test('la invitación recordada se acepta al iniciar sesión', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('alice@micliente.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    $alice = User::factory()->create(['account_id' => null, 'email' => 'alice@micliente.com']);

    Tenant::forget();

    $this->get(route('base-tenant.invitations.accept', ['token' => $invitacion->token]))
        ->assertRedirect(route('base-tenant.login'))
        ->assertSessionHas(InvitationService::PENDING_TOKEN_KEY, $invitacion->token);

    event(new Login('web', $alice, false));

    expect($alice->fresh()->belongsToAccount($cuenta))->toBeTrue()
        ->and($invitacion->fresh()->accepted_at)->not->toBeNull()
        ->and(session()->has(InvitationService::PENDING_TOKEN_KEY))->toBeFalse();
});

test('la invitación recordada no se acepta si entra otra persona', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('alice@micliente.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    $bob = User::factory()->create(['account_id' => null, 'email' => 'bob@gmail.com']);

    session([InvitationService::PENDING_TOKEN_KEY => $invitacion->token]);

    event(new Login('web', $bob, false));

    expect($bob->fresh()->belongsToAccount($cuenta))->toBeFalse()
        ->and($invitacion->fresh()->accepted_at)->toBeNull();
});

// ---------------------------------------------------------------------
// Rangos de IP
// ---------------------------------------------------------------------

test('una IP suelta coincide consigo misma y con nada más', function () {
    expect(IpRange::matches('203.0.113.4', '203.0.113.4'))->toBeTrue()
        ->and(IpRange::matches('203.0.113.5', '203.0.113.4'))->toBeFalse();
});

test('los rangos CIDR se resuelven', function (string $ip, string $rango, bool $dentro) {
    expect(IpRange::matches($ip, $rango))->toBe($dentro);
})->with([
    ['198.51.100.10', '198.51.100.0/24', true],
    ['198.51.100.255', '198.51.100.0/24', true],
    ['198.51.101.1', '198.51.100.0/24', false],
    ['10.0.0.1', '10.0.0.0/8', true],
    ['11.0.0.1', '10.0.0.0/8', false],
    ['192.168.1.130', '192.168.1.128/25', true],
    ['192.168.1.127', '192.168.1.128/25', false],
    ['2001:db8::1', '2001:db8::/32', true],
    ['2001:db9::1', '2001:db8::/32', false],
]);

/**
 * Dos formas de escribir la misma dirección son la misma dirección. Comparar
 * el texto tal cual dejaba fuera a una oficina entera porque su administrador
 * escribió la IPv6 como la imprime el router, y a cualquiera detrás de un
 * servidor que entrega `::ffff:a.b.c.d` en lugar de `a.b.c.d`.
 */
test('las distintas grafías de una misma dirección coinciden', function (string $ip, string $entrada) {
    expect(IpRange::matches($ip, $entrada))->toBeTrue();
})->with([
    'IPv6 expandida y en mayúsculas' => ['2001:db8::1', '2001:0DB8:0000:0000:0000:0000:0000:0001'],
    'IPv6 comprimida en la lista, expandida en la petición' => ['2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
    'IPv4 embebida en IPv6 contra una IPv4' => ['::ffff:203.0.113.4', '203.0.113.4'],
    'IPv4 embebida en IPv6 contra un rango IPv4' => ['::ffff:198.51.100.10', '198.51.100.0/24'],
    'IPv4 contra su forma embebida en la lista' => ['203.0.113.4', '::ffff:203.0.113.4'],
    'rango embebido contra una IPv4' => ['198.51.100.10', '::ffff:198.51.100.0/120'],
]);

/**
 * Mezclar familias no es un error, es simplemente que no coincide. Devolver
 * una excepción aquí convertiría una entrada IPv6 en una caída del middleware.
 */
test('mezclar IPv4 con un rango IPv6 no coincide y no revienta', function () {
    expect(IpRange::matches('203.0.113.4', '2001:db8::/32'))->toBeFalse();
});

test('las entradas con errata se detectan antes de guardarlas', function (string $entrada, bool $valida) {
    expect(IpRange::isValidEntry($entrada))->toBe($valida);
})->with([
    ['203.0.113.4', true],
    ['198.51.100.0/24', true],
    ['2001:db8::/32', true],
    ['203.0.113.999', false],
    ['198.51.100.0/33', false],
    ['203.0.113.4/', false],
    ['no-es-una-ip', false],
    ['', false],
    // Un rango IPv4 embebido más estrecho que el prefijo de 96 bits no cubre
    // ninguna dirección que este producto vaya a ver: el validador y el
    // comparador tienen que rechazarlo los dos, no sólo uno.
    ['::ffff:203.0.113.0/64', false],
    ['::ffff:203.0.113.0/120', true],
    ['2001:0DB8::1', true],
]);

test('lo que el validador acepta el comparador lo puede casar', function (string $entrada) {
    expect(IpRange::isValidEntry($entrada))->toBeTrue()
        ->and(IpRange::matches('203.0.113.5', $entrada))->toBeTrue();
})->with(['203.0.113.5', '203.0.113.0/24', '::ffff:203.0.113.5', '::ffff:203.0.113.0/120']);

// ---------------------------------------------------------------------
// La lista blanca aplicada
// ---------------------------------------------------------------------

/**
 * La guarda contra el auto-bloqueo. Una lista vacía en modo bloqueo dejaría
 * fuera a toda la cuenta, incluida la persona que acaba de configurarla, y no
 * hay forma de arreglarlo desde dentro del producto.
 */
test('una lista vacía nunca bloquea aunque el modo sea bloquear', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_ENFORCE,
        'ipAllowlist' => [],
    ]);

    expect(Security::allowsIp('203.0.113.4', $cuenta))->toBeTrue()
        ->and(Security::enforcesIp($cuenta))->toBeFalse();
});

test('con lista y modo bloquear, fuera de la lista no se pasa', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_ENFORCE,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);

    expect(Security::allowsIp('198.51.100.7', $cuenta))->toBeTrue()
        ->and(Security::allowsIp('203.0.113.4', $cuenta))->toBeFalse()
        ->and(Security::enforcesIp($cuenta))->toBeTrue();
});

/**
 * El modo aviso existe para que un administrador pueda encender la regla, mirar
 * un día de tráfico real y encontrar la VPN de la oficina que se le olvidó.
 */
test('el modo aviso no bloquea', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, [
        'ipMode' => SecurityPolicySettings::IP_WARN,
        'ipAllowlist' => ['198.51.100.0/24'],
    ]);

    expect(Security::warnsOnIp($cuenta))->toBeTrue()
        ->and(Security::enforcesIp($cuenta))->toBeFalse();
});

// ---------------------------------------------------------------------
// Doble factor
// ---------------------------------------------------------------------

test('encender la regla arranca el reloj de todos los miembros', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    expect($usuario->two_factor_required_from)->toBeNull();

    Security::update($cuenta, ['requireTwoFactor' => true]);

    expect($usuario->fresh()->two_factor_required_from)->not->toBeNull();
});

test('dentro del periodo de gracia todavía no se obliga', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['requireTwoFactor' => true, 'twoFactorGraceHours' => 72]);

    expect(Security::twoFactorIsOverdue($usuario->fresh(), $cuenta))->toBeFalse();
});

test('pasado el periodo de gracia se obliga', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['requireTwoFactor' => true, 'twoFactorGraceHours' => 1]);

    $usuario->forceFill(['two_factor_required_from' => now()->subHours(5)])->save();

    expect(Security::twoFactorIsOverdue($usuario->fresh(), $cuenta))->toBeTrue();
});

test('quien ya tiene doble factor nunca está fuera de plazo', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['requireTwoFactor' => true, 'twoFactorGraceHours' => 0]);

    $usuario->forceFill([
        'two_factor_required_from' => now()->subYear(),
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    expect(Security::twoFactorIsOverdue($usuario->fresh(), $cuenta))->toBeFalse();
});

/**
 * Apagar y volver a encender la regla no puede regalar un periodo de gracia
 * nuevo a quien ya gastó el suyo.
 */
test('reencender la regla no reinicia el reloj de quien ya lo tenía', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta);

    Security::update($cuenta, ['requireTwoFactor' => true]);
    $primera = $usuario->fresh()->two_factor_required_from;

    $this->travel(2)->hours();

    Security::update($cuenta, ['requireTwoFactor' => false]);
    Security::update($cuenta, ['requireTwoFactor' => true]);

    expect($usuario->fresh()->two_factor_required_from->timestamp)
        ->toBe($primera->timestamp);
});

/**
 * Quien entra en una cuenta que ya exige doble factor no tenía sello, y sin
 * él el plazo se contaría desde `created_at`: para un usuario de hace dos
 * años eso es un plazo ya vencido, y la primera petición lo echa a la pantalla
 * de perfil sin gracia alguna.
 */
test('quien se une a una cuenta que ya exige doble factor estrena su periodo de gracia', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $invitador = $this->createUser($cuenta);
    $rol = $this->createRole($cuenta, 'miembro');

    Security::update($cuenta, ['requireTwoFactor' => true, 'twoFactorGraceHours' => 72]);

    $veterano = User::factory()->create([
        'account_id' => null,
        'email' => 'veterano@micliente.com',
        'created_at' => now()->subYears(2),
    ]);

    $invitacion = Tenant::runFor(
        $cuenta,
        fn (): UserInvite => InvitationService::send('veterano@micliente.com', $cuenta->getKey(), $rol->id, $invitador)
    );

    InvitationService::accept($invitacion, $veterano);

    expect($veterano->fresh()->two_factor_required_from)->not->toBeNull()
        ->and(Security::twoFactorIsOverdue($veterano->fresh(), $cuenta))->toBeFalse();
});

test('unirse a una cuenta no reinicia el reloj de quien ya lo tenía en marcha', function () {
    $cuenta = $this->createAccount();
    Security::update($cuenta, ['requireTwoFactor' => true]);

    $usuario = User::factory()->create(['account_id' => null]);
    $usuario->forceFill(['two_factor_required_from' => now()->subDays(10)])->save();

    Security::startTwoFactorClockFor($usuario->fresh(), $cuenta);

    expect($usuario->fresh()->two_factor_required_from->timestamp)
        ->toBe(now()->subDays(10)->timestamp);
});

test('unirse a una cuenta sin la regla no sella nada', function () {
    $cuenta = $this->createAccount();
    $usuario = User::factory()->create(['account_id' => null]);

    Security::startTwoFactorClockFor($usuario, $cuenta);

    expect($usuario->fresh()->two_factor_required_from)->toBeNull();
});

// ---------------------------------------------------------------------
// La caché de la política
// ---------------------------------------------------------------------

/**
 * El gestor es un singleton: en un proceso que sirve una petición y muere la
 * caché dura lo que la petición, pero bajo Octane o un worker de colas duraría
 * lo que el proceso. `forget()` es lo que el proveedor llama al empezar cada
 * petición o job.
 */
test('la política se memoriza por petición y forget la vuelve a leer', function () {
    $cuenta = $this->createAccount();

    expect(Security::for($cuenta)->requireTwoFactor)->toBeFalse();

    // Escrito por debajo del gestor, como lo haría otro worker.
    SecurityPolicySettings::for($cuenta)->fill(['requireTwoFactor' => true])->save();

    expect(Security::for($cuenta)->requireTwoFactor)->toBeFalse();

    Security::forget();

    expect(Security::for($cuenta)->requireTwoFactor)->toBeTrue();
});

// ---------------------------------------------------------------------
// Auditoría
// ---------------------------------------------------------------------

/**
 * «Quién apagó la lista blanca y cuándo» es la primera pregunta después de un
 * incidente, y el almacén de ajustes no guarda historia.
 */
test('cada cambio de política queda auditado', function () {
    $cuenta = $this->createAccount();

    Security::update($cuenta, ['requireTwoFactor' => true]);

    $this->assertDatabaseHas('activity_log', ['action' => 'security.policy_changed']);
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

test('la pantalla exige permiso de lectura', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'pelado');
    $usuario = $this->createUser($cuenta, 'pelado');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)->assertForbidden();
});

test('con lectura pero sin escritura no se puede guardar', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'mirón', ['security.view']);
    $usuario = $this->createUser($cuenta, 'mirón');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)
        ->set('requireTwoFactor', true)
        ->call('save')
        ->assertForbidden();
});

/**
 * LA guarda de esta pantalla: pasar a modo bloqueo desde una dirección que la
 * lista no cubre deja a la cuenta fuera de su propio producto, y no hay vuelta
 * atrás desde dentro.
 */
test('no se puede activar el bloqueo desde una IP que no está en la lista', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'jefe', ['security.view', 'security.update']);
    $usuario = $this->createUser($cuenta, 'jefe');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)
        ->set('ipMode', 'enforce')
        ->set('ipAllowlist', '198.51.100.0/24')
        ->call('save')
        ->assertHasErrors('ipAllowlist');

    expect(Security::for($cuenta->fresh())->ipMode)->toBe(SecurityPolicySettings::IP_OFF);
});

test('con la propia dirección en la lista sí se puede activar el bloqueo', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'jefe', ['security.view', 'security.update']);
    $usuario = $this->createUser($cuenta, 'jefe');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)
        ->set('ipMode', 'enforce')
        ->call('addCurrentIp')
        ->call('save')
        ->assertHasNoErrors();

    expect(Security::for($cuenta->fresh())->ipMode)->toBe(SecurityPolicySettings::IP_ENFORCE);
});

test('una entrada con errata se rechaza antes de guardar', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'jefe', ['security.view', 'security.update']);
    $usuario = $this->createUser($cuenta, 'jefe');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)
        ->set('ipMode', 'warn')
        ->set('ipAllowlist', "203.0.113.4\n203.0.113.999")
        ->call('save')
        ->assertHasErrors('ipAllowlist');
});

test('los dominios se guardan normalizados desde la pantalla', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'jefe', ['security.view', 'security.update']);
    $usuario = $this->createUser($cuenta, 'jefe');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(SecurityPolicyManagerComponent::class)
        ->set('allowedEmailDomains', " @MiCliente.COM \nada@otro.com ")
        ->call('save')
        ->assertHasNoErrors();

    expect(Security::for($cuenta->fresh())->allowedEmailDomains)
        ->toBe(['micliente.com', 'otro.com']);
});
