<?php

declare(strict_types=1);

use Base\Tenant\Facades\Passkey as PasskeyFacade;
use Base\Tenant\Livewire\Profile\Passkeys as PasskeysComponent;
use Base\Tenant\Models\Passkey;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Una credencial ya guardada, con la forma que la librería serializa. No es un
 * `CredentialRecord` real -- para eso hace falta un autenticador de verdad --
 * pero sirve para todo lo que estos tests comprueban: alcance, propiedad,
 * autorización y las rutas.
 */
function passkeyDe($usuario, string $nombre = 'MacBook', array $atributos = []): Passkey
{
    return Passkey::create([
        'user_id' => $usuario->getKey(),
        'credential_id' => Passkey::encodeId(random_bytes(32)),
        'name' => $nombre,
        'record' => ['publicKeyCredentialId' => 'x', 'type' => 'public-key'],
        ...$atributos,
    ]);
}

// ---------------------------------------------------------------------
// Codificación
// ---------------------------------------------------------------------

/**
 * El navegador habla base64url y el servidor también tiene que hacerlo.
 * Mezclarlo con base64 normal es la razón habitual de que una primera
 * implementación de passkeys falle en silencio.
 */
test('el identificador de credencial va y vuelve en base64url', function () {
    $crudo = random_bytes(64);

    $codificado = Passkey::encodeId($crudo);

    expect($codificado)->not->toContain('+')
        ->and($codificado)->not->toContain('/')
        ->and($codificado)->not->toContain('=')
        ->and(Passkey::decodeId($codificado))->toBe($crudo);
});

// ---------------------------------------------------------------------
// Opciones de ceremonia
// ---------------------------------------------------------------------

test('las opciones de registro llevan reto, usuario y algoritmos', function () {
    $usuario = $this->createUser();

    $opciones = PasskeyFacade::creationOptions($usuario);

    expect($opciones)->toBeInstanceOf(PublicKeyCredentialCreationOptions::class)
        ->and(strlen($opciones->challenge))->toBe(32)
        ->and($opciones->user->id)->toBe((string) $usuario->getKey())
        ->and($opciones->pubKeyCredParams)->toHaveCount(2);
});

/**
 * Sin esto, volver a registrar el mismo autenticador crea una segunda
 * credencial que el usuario no distingue de la primera.
 */
test('las opciones de registro excluyen las passkeys que ya tiene', function () {
    $usuario = $this->createUser();
    passkeyDe($usuario);
    passkeyDe($usuario, 'YubiKey');

    expect(PasskeyFacade::creationOptions($usuario)->excludeCredentials)->toHaveCount(2);
});

test('cada reto es distinto del anterior', function () {
    $usuario = $this->createUser();

    $primero = PasskeyFacade::creationOptions($usuario)->challenge;
    $segundo = PasskeyFacade::creationOptions($usuario)->challenge;

    expect($primero)->not->toBe($segundo);
});

/**
 * Sin usuario, el navegador ofrece lo que tenga para este origen. Eso es lo
 * que hace útil a una passkey, y además impide usar el endpoint para preguntar
 * si una dirección es de un cliente.
 */
test('las opciones de login no piden usuario ni delatan a ninguno', function () {
    $opciones = PasskeyFacade::requestOptions();

    expect($opciones)->toBeInstanceOf(PublicKeyCredentialRequestOptions::class)
        ->and($opciones->allowCredentials)->toBe([]);
});

/**
 * Una passkey se ata al origen donde se creó. Mover el relying party al
 * dominio de cada cliente invalidaría todas las llaves en cuanto alguien
 * cambiase su dominio.
 */
test('el relying party sale de la app, nunca del dominio del cliente', function () {
    config(['app.url' => 'https://producto.test', 'base-tenant.passwordless.passkeys.relying_party_id' => null]);

    expect(PasskeyFacade::relyingPartyId())->toBe('producto.test');

    config(['base-tenant.passwordless.passkeys.relying_party_id' => 'fijado.test']);

    expect(PasskeyFacade::relyingPartyId())->toBe('fijado.test');
});

// ---------------------------------------------------------------------
// Verificación
// ---------------------------------------------------------------------

test('una credencial basura no verifica y no revienta', function () {
    expect(PasskeyFacade::verify('{"no":"es esto"}', PasskeyFacade::requestOptions(), 'producto.test'))
        ->toBeNull();
});

test('una credencial que no existe no verifica', function () {
    expect(PasskeyFacade::verify(
        json_encode(['id' => 'x', 'rawId' => 'eA', 'type' => 'public-key', 'response' => []]),
        PasskeyFacade::requestOptions(),
        'producto.test'
    ))->toBeNull();
});

// ---------------------------------------------------------------------
// Propiedad
// ---------------------------------------------------------------------

test('cada quien ve sólo sus passkeys', function () {
    $mio = $this->createUser();
    $ajeno = $this->createUser();

    passkeyDe($mio);
    passkeyDe($ajeno, 'Suya');

    $this->actingAsTenant($mio);

    Livewire::test(PasskeysComponent::class)
        ->assertViewHas('passkeys', fn ($llaves): bool => $llaves->count() === 1);
});

/**
 * `Passkey` no lleva scope global -- pertenece a una persona, no a una cuenta
 * --, así que el acotado lo hace la pantalla a mano. Esta es la prueba.
 */
test('no se puede borrar la passkey de otro', function () {
    $mio = $this->createUser();
    $ajeno = $this->createUser();

    $suya = passkeyDe($ajeno, 'Suya');

    $this->actingAsTenant($mio);

    expect(fn () => Livewire::test(PasskeysComponent::class)->call('remove', $suya->id))
        ->toThrow(ModelNotFoundException::class);

    expect(Passkey::find($suya->id))->not->toBeNull();
});

test('no se puede renombrar la passkey de otro', function () {
    $mio = $this->createUser();
    $ajeno = $this->createUser();

    $suya = passkeyDe($ajeno, 'Suya');

    $this->actingAsTenant($mio);

    expect(fn () => Livewire::test(PasskeysComponent::class)->call('rename', $suya->id, 'Mia'))
        ->toThrow(ModelNotFoundException::class);

    expect($suya->fresh()->name)->toBe('Suya');
});

test('renombrar y borrar la propia funciona', function () {
    $usuario = $this->createUser();
    $llave = passkeyDe($usuario);

    $this->actingAsTenant($usuario);

    Livewire::test(PasskeysComponent::class)
        ->call('rename', $llave->id, 'Portátil del trabajo')
        ->call('remove', $llave->id);

    expect(Passkey::find($llave->id))->toBeNull();
});

test('un nombre vacío no borra el que había', function () {
    $usuario = $this->createUser();
    $llave = passkeyDe($usuario, 'Original');

    $this->actingAsTenant($usuario);

    Livewire::test(PasskeysComponent::class)->call('rename', $llave->id, '   ');

    expect($llave->fresh()->name)->toBe('Original');
});

// ---------------------------------------------------------------------
// Las rutas
// ---------------------------------------------------------------------

test('pedir opciones de registro exige sesión', function () {
    $this->postJson(route('base-tenant.passkeys.register-options'))->assertUnauthorized();
});

test('con sesión, las opciones de registro llegan y el reto queda guardado', function () {
    $usuario = $this->createUser();
    $this->actingAsTenant($usuario);

    $this->postJson(route('base-tenant.passkeys.register-options'))
        ->assertOk()
        ->assertJsonStructure(['challenge', 'rp', 'user']);

    expect(session()->has('base-tenant.passkey.creation'))->toBeTrue();
});

/**
 * Un reto que el servidor no recuerda es un reto que puede elegir el atacante.
 */
test('registrar sin reto en sesión se rechaza', function () {
    $usuario = $this->createUser();
    $this->actingAsTenant($usuario);

    $this->postJson(route('base-tenant.passkeys.register'), [
        'credential' => '{}',
        'name' => 'Cualquiera',
    ])->assertStatus(422);
});

test('las opciones de login no exigen sesión', function () {
    $this->postJson(route('base-tenant.passkeys.login-options'))
        ->assertOk()
        ->assertJsonStructure(['challenge']);
});

test('entrar con una credencial que no verifica se rechaza sin dar pistas', function () {
    $this->postJson(route('base-tenant.passkeys.login-options'));

    $this->postJson(route('base-tenant.passkeys.login'), ['credential' => '{"basura":true}'])
        ->assertStatus(422);

    expect(auth()->check())->toBeFalse();
});

// ---------------------------------------------------------------------
// El interruptor
// ---------------------------------------------------------------------

test('con las passkeys apagadas los endpoints no existen', function () {
    config(['base-tenant.passwordless.passkeys.enabled' => false]);

    $this->postJson(route('base-tenant.passkeys.login-options'))->assertNotFound();
});
