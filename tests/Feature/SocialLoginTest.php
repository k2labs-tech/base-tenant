<?php

declare(strict_types=1);

use Base\Tenant\Livewire\Profile\ConnectedAccounts;
use Base\Tenant\Models\SocialAccount;
use Base\Tenant\Models\User;
use Base\Tenant\Social\SocialAuthException;
use Base\Tenant\Social\SocialLoginService;
use Base\Tenant\Social\SocialProviders;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    $this->syncPermissions();

    // Presencia de credenciales igual a proveedor activo: no hay un segundo
    // interruptor que pueda quedarse desfasado.
    config([
        'services.google' => ['client_id' => 'id', 'client_secret' => 'secreto', 'redirect' => '/x'],
        'services.linkedin-openid' => ['client_id' => 'id', 'client_secret' => 'secreto', 'redirect' => '/x'],
        'base-tenant.social.allowed_domains' => [],
    ]);
});

/**
 * Una identidad como la que devuelve el proveedor.
 */
function identidadSocial(string $id, string $email, string $nombre = 'Ada Lovelace'): SocialiteUser
{
    $identidad = new SocialiteUser;

    $identidad->map([
        'id' => $id,
        'email' => $email,
        'name' => $nombre,
        'avatar' => 'https://ejemplo.test/avatar.png',
    ]);

    $identidad->token = 'token-de-acceso';
    $identidad->refreshToken = 'token-de-refresco';
    $identidad->expiresIn = 3600;

    return $identidad;
}

test('solo se ofrecen los proveedores con credenciales', function () {
    expect(SocialProviders::enabled())->toBe(['google', 'linkedin'])
        ->and(SocialProviders::configured('microsoft'))->toBeFalse();
});

test('sin el módulo no se ofrece ninguno', function () {
    config(['base-tenant.social.enabled' => false]);

    expect(SocialProviders::enabled())->toBe([]);
});

test('entrar por primera vez crea el usuario, su cuenta y el vínculo', function () {
    $usuaria = app(SocialLoginService::class)->authenticate(
        'google',
        identidadSocial('g-1', 'ada@ejemplo.test'),
    );

    expect($usuaria->email)->toBe('ada@ejemplo.test')
        ->and($usuaria->name)->toBe('Ada Lovelace')
        // El proveedor ya ha probado la dirección: pedirle que la pruebe otra
        // vez por correo es pedirle trabajo ya hecho.
        ->and($usuaria->email_verified_at)->not->toBeNull()
        // Sin contraseña, no con una aleatoria que nadie conoce.
        ->and($usuaria->password)->toBeNull()
        // Y con la misma cuenta primaria y el mismo rol que el registro normal.
        ->and($usuaria->accounts()->count())->toBe(1);

    expect(SocialAccount::where('user_id', $usuaria->getKey())->count())->toBe(1);
});

test('volver a entrar reusa el vínculo y no crea otro usuario', function () {
    $primera = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));
    $segunda = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    expect($segunda->getKey())->toBe($primera->getKey())
        ->and(User::count())->toBe(1)
        ->and(SocialAccount::count())->toBe(1);
});

/**
 * La regla que más importa. Si el correo ya tiene dueño con contraseña y nadie
 * ha dicho que sean la misma persona, vincular aquí significaría que quien
 * pueda crear una identidad en el proveedor con esa dirección se queda la
 * cuenta. En varios proveedores, probar la dirección ni siquiera hace falta.
 */
test('un correo ya registrado no se auto-vincula desde el login', function () {
    $cuenta = $this->createAccount();
    $existente = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);

    expect(fn () => app(SocialLoginService::class)->authenticate(
        'google',
        identidadSocial('g-intruso', 'ada@ejemplo.test'),
    ))->toThrow(SocialAuthException::class, 'ada@ejemplo.test');

    expect(SocialAccount::count())->toBe(0)
        ->and(User::count())->toBe(1)
        ->and(User::first()->getKey())->toBe($existente->getKey());
});

test('el mismo correo sí se vincula desde una sesión ya iniciada', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);

    $vinculo = app(SocialLoginService::class)->link(
        $usuaria,
        'google',
        identidadSocial('g-1', 'ada@ejemplo.test'),
    );

    expect($vinculo->user_id)->toBe($usuaria->getKey());
});

test('una identidad ya vinculada a otro usuario no se roba', function () {
    $cuenta = $this->createAccount();
    $duena = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);
    $otra = $this->createUser($cuenta, null, ['email' => 'otra@ejemplo.test']);

    app(SocialLoginService::class)->link($duena, 'google', identidadSocial('g-1', 'ada@ejemplo.test'));

    expect(fn () => app(SocialLoginService::class)->link($otra, 'google', identidadSocial('g-1', 'ada@ejemplo.test')))
        ->toThrow(SocialAuthException::class);

    expect(SocialAccount::where('provider_id', 'g-1')->first()->user_id)->toBe($duena->getKey());
});

test('un dominio fuera de la lista permitida no puede registrarse', function () {
    config(['base-tenant.social.allowed_domains' => ['empresa.test']]);

    expect(fn () => app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@gmail.test')))
        ->toThrow(SocialAuthException::class, 'gmail.test');

    $permitida = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-2', 'ada@empresa.test'));

    expect($permitida->email)->toBe('ada@empresa.test');
});

test('un proveedor que no devuelve correo no sirve para entrar', function () {
    $identidad = new SocialiteUser;
    $identidad->map(['id' => 'g-1', 'email' => null, 'name' => 'Ada']);

    expect(fn () => app(SocialLoginService::class)->authenticate('google', $identidad))
        ->toThrow(SocialAuthException::class);
});

/**
 * Los tokens son un permiso permanente para actuar como la persona en el
 * proveedor: un volcado de la base de datos no debería serlo.
 */
test('los tokens se guardan cifrados', function () {
    $usuaria = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    $vinculo = SocialAccount::where('user_id', $usuaria->getKey())->first();

    expect($vinculo->token)->toBe('token-de-acceso');

    $enBruto = DB::table('social_accounts')
        ->where('id', $vinculo->getKey())
        ->value('token');

    expect($enBruto)->not->toBe('token-de-acceso')
        ->and($enBruto)->not->toContain('token-de-acceso');
});

/**
 * Quitar el último proveedor de quien nunca puso contraseña dejaría una cuenta
 * que nadie puede abrir: ni siquiera restableciendo, porque restablecer
 * necesita una contraseña que restablecer.
 */
test('desvincular el último acceso queda bloqueado', function () {
    $usuaria = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    expect(fn () => app(SocialLoginService::class)->unlink($usuaria, 'google'))
        ->toThrow(SocialAuthException::class);

    expect(SocialAccount::count())->toBe(1);
});

test('con contraseña puesta sí se puede desvincular', function () {
    $usuaria = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    $usuaria->update(['password' => bcrypt('una-contraseña')]);

    app(SocialLoginService::class)->unlink($usuaria->fresh(), 'google');

    expect(SocialAccount::count())->toBe(0);
});

test('con dos proveedores se puede soltar uno aunque no haya contraseña', function () {
    $usuaria = app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    app(SocialLoginService::class)->link($usuaria, 'linkedin', identidadSocial('l-1', 'ada@ejemplo.test'));

    app(SocialLoginService::class)->unlink($usuaria, 'google');

    expect(SocialAccount::where('user_id', $usuaria->getKey())->pluck('provider')->all())->toBe(['linkedin']);
});

/**
 * Un usuario sin contraseña no puede entrar por el formulario. El hasher ya
 * rechaza un hash vacío, pero conviene que esté escrito.
 */
test('sin contraseña no se entra por el formulario', function () {
    app(SocialLoginService::class)->authenticate('google', identidadSocial('g-1', 'ada@ejemplo.test'));

    expect(auth()->attempt(['email' => 'ada@ejemplo.test', 'password' => '']))->toBeFalse()
        ->and(auth()->attempt(['email' => 'ada@ejemplo.test', 'password' => 'loquesea']))->toBeFalse();
});

test('el login enseña los proveedores configurados', function () {
    $this->withoutVite();

    $this->get(route('base-tenant.login'))
        ->assertOk()
        ->assertSee('data-social-provider="google"', false)
        ->assertSee('data-social-provider="linkedin"', false)
        ->assertDontSee('data-social-provider="microsoft"', false);
});

test('la ruta de un proveedor sin configurar no existe', function () {
    $this->get(route('base-tenant.social.redirect', ['provider' => 'microsoft']))
        ->assertNotFound();

    $this->get(route('base-tenant.social.redirect', ['provider' => 'inventado']))
        ->assertNotFound();
});

test('la vuelta del proveedor deja la sesión iniciada', function () {
    $this->mock(Factory::class, function (MockInterface $mock) {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn(identidadSocial('g-1', 'ada@ejemplo.test'));

        $mock->shouldReceive('driver')->with('google')->andReturn($driver);
    });

    $this->get(route('base-tenant.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('base-tenant.dashboard'));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('ada@ejemplo.test');
});

/**
 * El segundo factor va antes de la sesión. Iniciarla y desafiar después
 * dejaría la sesión existiendo mientras el desafío está pendiente, que es
 * justo para lo que sirve el segundo factor.
 */
test('la vuelta respeta el segundo factor', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);
    $usuaria->update(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_confirmed_at' => now()]);

    app(SocialLoginService::class)->link($usuaria->fresh(), 'google', identidadSocial('g-1', 'ada@ejemplo.test'));

    $this->mock(Factory::class, function (MockInterface $mock) {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn(identidadSocial('g-1', 'ada@ejemplo.test'));

        $mock->shouldReceive('driver')->with('google')->andReturn($driver);
    });

    $this->get(route('base-tenant.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('base-tenant.two-factor.challenge'));

    expect(auth()->check())->toBeFalse()
        ->and(session('2fa.user_id'))->toBe($usuaria->getKey());
});

test('el perfil deja conectar y desconectar', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);

    $this->actingAsTenant($usuaria, $cuenta);

    app(SocialLoginService::class)->link($usuaria, 'google', identidadSocial('g-1', 'ada@ejemplo.test'));

    Livewire::test(ConnectedAccounts::class)
        ->assertSee(__('base-tenant::social.connected'))
        ->assertSeeHtml('data-social-state="connected"')
        ->call('disconnect', 'google');

    expect(SocialAccount::count())->toBe(0);
});
