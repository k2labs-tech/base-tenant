<?php

declare(strict_types=1);

use Base\Tenant\Facades\Menu;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\AccountSwitcher;
use Base\Tenant\Livewire\NavigationManager;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

/**
 * El administrador de plataforma: sin cuenta propia y sin pivot.
 */
function administradorDePlataforma(): User
{
    return User::create([
        'is_admin' => true,
        'account_id' => null,
        'name' => 'Administrator',
        'email' => 'admin@ejemplo.test',
        'password' => bcrypt('x'),
        'email_verified_at' => now(),
    ]);
}

test('quien es de una cuenta solo ve la suya', function () {
    $cuenta = $this->createAccount(['name' => 'La Mía']);
    $this->createAccount(['name' => 'La Ajena']);

    $usuaria = $this->createUser($cuenta);

    $this->actingAsTenant($usuaria, $cuenta);

    $componente = Livewire::test(AccountSwitcher::class);

    expect($componente->viewData('accounts')->pluck('name')->all())->toBe(['La Mía']);
});

/**
 * El fallo que cerramos: al personal de plataforma se le devolvía una lista
 * vacía a propósito, así que no podía ponerse en ninguna cuenta y toda
 * pantalla con datos de cuenta le respondía 403 al intentar editar.
 */
test('el personal de plataforma ve todas las cuentas', function () {
    $this->createAccount(['name' => 'Aaa Empresa']);
    $this->createAccount(['name' => 'Zzz Empresa']);

    $this->actingAs(administradorDePlataforma());

    $componente = Livewire::test(AccountSwitcher::class);

    expect($componente->viewData('accounts')->pluck('name')->all())
        ->toBe(['Aaa Empresa', 'Zzz Empresa'])
        ->and($componente->viewData('isStaff'))->toBeTrue();
});

test('el personal de plataforma puede entrar en cualquier cuenta', function () {
    $cuenta = $this->createAccount(['name' => 'Cliente']);
    $admin = administradorDePlataforma();

    $this->actingAs($admin);

    Livewire::test(AccountSwitcher::class)->call('switchAccount', $cuenta->getKey());

    expect(Tenant::currentId())->toBe($cuenta->getKey())
        ->and($admin->fresh()->last_account_id)->toBe($cuenta->getKey());
});

/**
 * La comprobación va en la acción y no solo en la lista: el id llega del
 * navegador, y una lista que omite una cuenta no impide a nadie nombrarla.
 */
test('quien no es de una cuenta no entra aunque nombre su id', function () {
    $mia = $this->createAccount(['name' => 'La Mía']);
    $ajena = $this->createAccount(['name' => 'La Ajena']);

    $usuaria = $this->createUser($mia);

    $this->actingAsTenant($usuaria, $mia);

    Livewire::test(AccountSwitcher::class)->call('switchAccount', $ajena->getKey());

    expect(Tenant::currentId())->toBe($mia->getKey())
        ->and($usuaria->fresh()->last_account_id)->not->toBe($ajena->getKey());
});

/**
 * Va a actuar sobre datos que no son suyos, y el sitio donde eso consta es el
 * registro de la cuenta en la que entra.
 */
test('entrar desde plataforma deja constancia en la cuenta', function () {
    $cuenta = $this->createAccount(['name' => 'Cliente']);

    $this->actingAs(administradorDePlataforma());

    Livewire::test(AccountSwitcher::class)->call('switchAccount', $cuenta->getKey());

    $entrada = ActivityLog::query()->forAccount($cuenta)->where('action', 'account.entered')->first();

    expect($entrada)->not->toBeNull()
        ->and($entrada->description)->toContain('Administrator');
});

test('un cambio de cuenta normal no ensucia el registro', function () {
    config(['base-tenant.multi_team' => true]);

    $una = $this->createAccount(['name' => 'Una']);
    $otra = $this->createAccount(['name' => 'Otra']);

    $usuaria = $this->createUser($una);
    $usuaria->accounts()->syncWithoutDetaching([$otra->getKey()]);

    $this->actingAsTenant($usuaria, $una);

    Livewire::test(AccountSwitcher::class)->call('switchAccount', $otra->getKey());

    expect(ActivityLog::query()->acrossAccounts()->where('action', 'account.entered')->count())->toBe(0);
});

/**
 * Sin salida, entrar en una cuenta sería un viaje de ida: el resolver recuerda
 * la última y no habría forma de volver a la vista de plataforma.
 */
test('el personal de plataforma puede volver a salir', function () {
    $cuenta = $this->createAccount();
    $admin = administradorDePlataforma();

    $this->actingAs($admin);

    $componente = Livewire::test(AccountSwitcher::class);
    $componente->call('switchAccount', $cuenta->getKey());
    $componente->call('leaveAccount');

    expect(Tenant::currentId())->toBeNull()
        ->and($admin->fresh()->last_account_id)->toBeNull();
});

test('quien no es de plataforma no puede quedarse sin cuenta', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    $usuaria->forceFill(['last_account_id' => $cuenta->getKey()])->save();

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(AccountSwitcher::class)->call('leaveAccount');

    expect($usuaria->fresh()->last_account_id)->toBe($cuenta->getKey())
        ->and(Tenant::currentId())->toBe($cuenta->getKey());
});

/**
 * La prueba que cierra el informe: la acción que devolvía 403 al administrador
 * de plataforma funciona en cuanto está dentro de una cuenta.
 */
test('dentro de una cuenta el administrador ya puede editar su menú', function () {
    $cuenta = $this->createAccount();
    $admin = administradorDePlataforma();

    // Los menús por defecto tienen que existir para poder sobrescribir uno.
    Menu::sync();

    $this->actingAs($admin);

    // Sin cuenta: la pantalla abre, pero editar se niega.
    Livewire::test(NavigationManager::class)
        ->assertOk()
        ->call('toggle', 'dashboard')
        ->assertForbidden();

    Livewire::test(AccountSwitcher::class)->call('switchAccount', $cuenta->getKey());

    Livewire::test(NavigationManager::class)
        ->call('toggle', 'dashboard')
        ->assertOk();
});

test('el buscador acota la lista del personal de plataforma', function () {
    $this->createAccount(['name' => 'Alfa']);
    $this->createAccount(['name' => 'Beta']);

    $this->actingAs(administradorDePlataforma());

    $componente = Livewire::test(AccountSwitcher::class)->set('search', 'alf');

    expect($componente->viewData('accounts')->pluck('name')->all())->toBe(['Alfa']);
});

/**
 * Un desplegable de cuatro mil filas no se dibuja para nada: el tope es lo que
 * hace que el buscador sea necesario en vez de decorativo.
 */
test('la lista del personal de plataforma está acotada', function () {
    for ($i = 0; $i < AccountSwitcher::SEARCH_THRESHOLD * 5 + 3; $i++) {
        $this->createAccount(['name' => sprintf('Empresa %03d', $i)]);
    }

    $this->actingAs(administradorDePlataforma());

    $componente = Livewire::test(AccountSwitcher::class);

    expect($componente->viewData('accounts'))->toHaveCount(AccountSwitcher::SEARCH_THRESHOLD * 5)
        ->and($componente->viewData('searchable'))->toBeTrue();
});
