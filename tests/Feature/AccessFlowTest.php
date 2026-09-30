<?php

declare(strict_types=1);

use Base\Tenant\Livewire\Attributes\GuestLayout;
use Base\Tenant\Livewire\Auth\ConfirmPassword;
use Base\Tenant\Livewire\Auth\Login;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->withoutVite();

    $this->syncPermissions();
});

// ------------------------------------------------------ Layout de invitado

test('las pantallas de acceso usan por defecto el layout del paquete', function () {
    expect(GuestLayout::view())->toBe('base-tenant::layouts.guest');

    $this->get(route('base-tenant.login'))
        ->assertOk()
        ->assertDontSee('data-host-guest-layout', escape: false);
});

test('las pantallas de acceso obedecen layouts.guest', function (string $ruta) {
    View::addNamespace('anfitrion', dirname(__DIR__).'/Fixtures/views');
    config(['base-tenant.layouts.guest' => 'anfitrion::guest-layout']);

    $this->get(route($ruta))
        ->assertOk()
        ->assertSee('data-host-guest-layout', escape: false);
})->with([
    'login' => 'base-tenant.login',
    'registro' => 'base-tenant.register',
    'contraseña olvidada' => 'base-tenant.password.request',
]);

test('el reto del segundo factor también obedece layouts.guest', function () {
    View::addNamespace('anfitrion', dirname(__DIR__).'/Fixtures/views');
    config(['base-tenant.layouts.guest' => 'anfitrion::guest-layout']);

    $usuario = $this->createUser();

    $this->withSession(['2fa.user_id' => $usuario->getKey()])
        ->get(route('base-tenant.two-factor.challenge'))
        ->assertOk()
        ->assertSee('data-host-guest-layout', escape: false);
});

// ------------------------------------------------------ Destino tras entrar

test('el login lleva a home_url', function () {
    config(['base-tenant.home_url' => 'base-tenant.profile']);

    $this->createUser(attributes: [
        'email' => 'titular@ejemplo.test',
        'password' => Hash::make('contrasena-correcta'),
    ]);

    Livewire::test(Login::class)
        ->set('form.email', 'titular@ejemplo.test')
        ->set('form.password', 'contrasena-correcta')
        ->call('login')
        ->assertRedirect('/profile');
});

test('confirmar la contraseña lleva a home_url', function () {
    config(['base-tenant.home_url' => '/panel']);

    $this->actingAs($this->createUser());

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'password')
        ->call('confirmPassword')
        ->assertRedirect('/panel');
});

test('el segundo factor lleva a home_url', function () {
    config(['base-tenant.home_url' => '/panel']);

    $google2fa = new Google2FA;
    $secreto = $google2fa->generateSecretKey();

    $usuario = $this->createUser(attributes: [
        'two_factor_secret' => encrypt($secreto),
        'two_factor_confirmed_at' => now(),
    ]);

    session(['2fa.user_id' => $usuario->getKey()]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $google2fa->getCurrentOtp($secreto))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    expect(auth()->id())->toBe($usuario->getKey());
});

test('ningún destino tras entrar escribe el panel a mano', function () {
    $raiz = dirname(__DIR__, 2);
    $ficheros = [
        ...glob($raiz.'/src/Livewire/Auth/*.php'),
        ...glob($raiz.'/src/Http/Controllers/Auth/*.php'),
        $raiz.'/src/Livewire/TwoFactorChallenge.php',
        $raiz.'/src/Livewire/AcceptTerms.php',
        $raiz.'/src/Livewire/AccountSwitcher.php',
        $raiz.'/src/Livewire/UserManager.php',
        $raiz.'/src/Livewire/Profile/UpdateProfileInformationForm.php',
        $raiz.'/src/Http/Controllers/InvitationAcceptController.php',
        $raiz.'/src/Http/Middleware/DoesNotHaveSubscription.php',
    ];

    foreach ($ficheros as $fichero) {
        expect(file_get_contents($fichero))
            ->not->toContain("'base-tenant.dashboard'", basename($fichero));
    }
});

// ------------------------------------------------------ Registro

function registrarTitular(): Testable
{
    return Livewire::test(Register::class)
        ->set('name', 'Titular')
        ->set('companyName', 'Acme')
        ->set('email', 'titular@ejemplo.test')
        ->set('password', 'Password-1234!')
        ->set('password_confirmation', 'Password-1234!')
        ->call('register');
}

test('sin suscripciones el registro lleva a home_url y no al checkout', function () {
    expect(Route::has('base-tenant.checkout'))->toBeFalse();

    registrarTitular()
        ->assertHasNoErrors()
        ->assertRedirect(route('base-tenant.dashboard'));
});

test('con suscripciones el registro sigue llevando al checkout', function () {
    config(['base-tenant.subscription.enabled' => true]);
    Route::middleware('web')->get('checkout', fn () => 'checkout')->name('base-tenant.checkout');
    app('router')->getRoutes()->refreshNameLookups();

    registrarTitular()->assertRedirect(route('base-tenant.checkout'));
});

test('el registro crea la cuenta con su titular como propietario', function () {
    registrarTitular()->assertHasNoErrors();

    $usuario = User::where('email', 'titular@ejemplo.test')->firstOrFail();
    $cuenta = Account::findOrFail($usuario->account_id);

    expect($cuenta->name)->toBe('Acme')
        ->and((string) $cuenta->user_id)->toBe((string) $usuario->getKey())
        ->and($usuario->rolesForAccount($cuenta)->pluck('name')->all())->toBe(['customer-admin'])
        ->and(auth()->id())->toBe($usuario->getKey());
});

test('si el rol de propietario falla no queda ni usuario ni cuenta', function () {
    Role::query()->where('name', 'customer-admin')->delete();
    $cuentasAntes = Account::query()->count();

    expect(fn () => registrarTitular())->toThrow(LogicException::class);

    expect(User::where('email', 'titular@ejemplo.test')->exists())->toBeFalse()
        ->and(Account::query()->count())->toBe($cuentasAntes)
        ->and(auth()->check())->toBeFalse();
});

// ------------------------------------------------------ Segundo factor

test('el reto no deja cambiar a quién se reta', function () {
    $retado = $this->createUser();
    $otro = $this->createUser();

    session(['2fa.user_id' => $retado->getKey()]);

    expect(fn () => Livewire::test(TwoFactorChallenge::class)->set('userId', $otro->getKey()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('los códigos de recuperación de Fortify se leen', function () {
    $codigos = ['abcde-fghij', 'klmno-pqrst'];
    $usuario = $this->createUser();

    DB::table('users')->where('id', $usuario->getKey())->update([
        'two_factor_recovery_codes' => encrypt(json_encode($codigos)),
    ]);

    expect($usuario->fresh()->two_factor_recovery_codes)->toBe($codigos);
});

test('gastar un código de Fortify lo invalida y deja el resto cifrado', function () {
    $usuario = $this->createUser();

    DB::table('users')->where('id', $usuario->getKey())->update([
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-fghij', 'klmno-pqrst'])),
    ]);

    $usuario = $usuario->fresh();

    expect($usuario->invalidateRecoveryCode('abcde-fghij'))->toBeTrue()
        ->and($usuario->invalidateRecoveryCode('abcde-fghij'))->toBeFalse();

    $guardado = DB::table('users')->where('id', $usuario->getKey())->value('two_factor_recovery_codes');

    expect(json_decode($guardado, true))->toBeNull()
        ->and(json_decode(decrypt($guardado), true))->toBe(['klmno-pqrst'])
        ->and($usuario->fresh()->two_factor_recovery_codes)->toBe(['klmno-pqrst']);
});

test('los códigos del paquete siguen guardándose como JSON', function () {
    $usuario = $this->createUser();
    $usuario->enableTwoFactorAuthentication('secreto');

    $guardado = DB::table('users')->where('id', $usuario->getKey())->value('two_factor_recovery_codes');

    expect(json_decode($guardado, true))->toBe($usuario->fresh()->two_factor_recovery_codes)
        ->and($usuario->fresh()->two_factor_recovery_codes)->toHaveCount(8);

    $usuario->disableTwoFactorAuthentication();

    expect($usuario->fresh()->two_factor_recovery_codes)->toBeNull();
});

test('un usuario que viene de Fortify entra con un código de recuperación', function () {
    // Mismas columnas y mismo cifrado del secreto que Fortify.
    $usuario = $this->createUser(attributes: [
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);

    DB::table('users')->where('id', $usuario->getKey())->update([
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde-fghij'])),
    ]);

    expect($usuario->fresh()->two_factor_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($usuario->fresh()->hasTwoFactorEnabled())->toBeTrue();

    session(['2fa.user_id' => $usuario->getKey()]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('usingRecoveryCode', true)
        ->set('recoveryCode', 'abcde-fghij')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('base-tenant.dashboard'));

    expect(auth()->id())->toBe($usuario->getKey())
        ->and($usuario->fresh()->two_factor_recovery_codes)->toBe([]);
});

test('un valor ilegible no rompe la lectura', function () {
    $usuario = $this->createUser();

    DB::table('users')->where('id', $usuario->getKey())->update([
        'two_factor_recovery_codes' => 'no-es-json-ni-esta-cifrado',
    ]);

    expect($usuario->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and($usuario->fresh()->invalidateRecoveryCode('lo-que-sea'))->toBeFalse();
});
