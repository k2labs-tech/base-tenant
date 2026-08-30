<?php

declare(strict_types=1);

use Base\Tenant\Facades\Settings;
use Base\Tenant\Livewire\AccountSettings;
use Base\Tenant\Livewire\RoleManager;
use Base\Tenant\Livewire\TwoFactorAuthentication;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Base\Tenant\Models\Role;
use Base\Tenant\Settings\SettingsSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * Las cuatro pantallas que quedaban sin migrar son las que más piezas sueltas
 * tenían: dos de ellas dibujaban sus propios `<input type="checkbox">` y sus
 * propias etiquetas, y la de dos factores levantaba tres modales con un
 * componente casero.
 *
 * Lo que se protege aquí es lo mismo de siempre: que cada error siga
 * llegando a la pantalla, que un permiso que el usuario no puede tocar siga
 * saliendo deshabilitado y no escondido, y que los modales sigan pidiendo la
 * contraseña antes de tocar el segundo factor.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
});

test('el gestor de roles guarda los permisos que se marcan', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $rol = $this->createRole($cuenta, 'auditor');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(RoleManager::class)
        ->call('edit', (string) $rol->getKey())
        ->set('selectedPermissions', ['users.view', 'activity.view'])
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($rol->refresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['activity.view', 'users.view']);
});

test('crear un rol sin nombre se queja en pantalla', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(RoleManager::class)
        ->call('toggleCreateForm')
        ->set('newRoleName', '')
        ->call('createRole')
        ->assertHasErrors('newRoleName');

    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($componente->errors()->first('newRoleName'));
});

/**
 * Un cliente no llega siquiera a ver la matriz de un rol global: `findRole()`
 * lo filtra y `edit()` autoriza después. La rama `@cannot` que la vista
 * conserva es una red de seguridad, no un camino que se pueda recorrer: se
 * mantiene por si la autorización cambia a mitad de sesión, pero lo que se
 * comprueba aquí es la puerta, que sí es alcanzable.
 */
test('un cliente no puede abrir un rol global', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $global = Role::query()->whereNull('account_id')->where('is_system', false)->firstOrFail();

    $this->actingAsTenant($admin, $cuenta);

    // `findRole()` ya filtra por `manageable()`, así que para un cliente el rol
    // global ni siquiera existe: la puerta se cierra antes de la autorización.
    expect(fn () => Livewire::test(RoleManager::class)->call('edit', (string) $global->getKey()))
        ->toThrow(ModelNotFoundException::class);
});

test('un administrador del sistema sí abre un rol global y lo ve editable', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, null, ['is_admin' => true]);

    $global = Role::query()->whereNull('account_id')->where('is_system', false)->firstOrFail();

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(RoleManager::class)->call('edit', (string) $global->getKey());

    $componente->assertSee($global->label);
    $componente->assertSeeHtml('wire:model="selectedPermissions"');
    $componente->assertDontSee(__('base-tenant::roles.read_only'));
});

test('los ajustes de cuenta guardan sus valores', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    Settings::register(AjustesDePrueba::class);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(AccountSettings::class)
        ->set('values.brand_name', 'Marca Nueva')
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::for($cuenta)->get(AjustesDePrueba::class)->brand_name)->toBe('Marca Nueva');
});

test('los ajustes de cuenta pintan cada tipo de campo', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    Settings::register(AjustesDePrueba::class);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(AccountSettings::class);

    $componente->assertSeeHtml('wire:model="values.brand_name"');
    $componente->assertSeeHtml('wire:model="values.max_items"');
    $componente->assertSeeHtml('wire:model="values.enabled"');
    $componente->assertSeeHtml('wire:model="values.payload"');
});

test('el segundo factor pide la contraseña antes de activarse', function () {
    $usuario = $this->createUser();

    $this->actingAs($usuario);

    $componente = Livewire::test(TwoFactorAuthentication::class)
        ->call('openEnableModal')
        ->set('confirmationCode', '123456')
        ->set('password', '')
        ->call('enable')
        ->assertHasErrors('password');

    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($componente->errors()->first('password'));
});

test('los tres modales del segundo factor siguen existiendo', function () {
    $usuario = $this->createUser();

    $this->actingAs($usuario);

    $componente = Livewire::test(TwoFactorAuthentication::class);

    // Flux le añade el modificador `.self` al `wire:model` de un modal, así que
    // el atributo que llega al HTML no es el que se escribe en la plantilla.
    $componente->assertSeeHtml('wire:model.self="showEnableModal"');
    $componente->assertSeeHtml('wire:model.self="showDisableModal"');
    $componente->assertSeeHtml('wire:model.self="showRecoveryCodesModal"');
});

test('el modal de desactivar el segundo factor va en rojo', function () {
    $usuario = $this->createUser();

    $this->actingAs($usuario);

    // `variant` lo consume Flux como prop y nunca llega al HTML: lo que sí
    // llega es el color que ese variant pinta.
    Livewire::test(TwoFactorAuthentication::class)->assertSeeHtml('bg-red-500');
});

test('el reto de dos factores se queja de un código vacío en pantalla', function () {
    $usuario = $this->createUser();

    $componente = Livewire::test(TwoFactorChallenge::class, ['userId' => $usuario->getKey()])
        ->set('code', '')
        ->call('verify')
        ->assertHasErrors('code');

    $componente->assertSeeHtml('data-flux-error');
    $componente->assertSee($componente->errors()->first('code'));
});

test('el reto de dos factores cambia a código de recuperación', function () {
    $usuario = $this->createUser();

    $componente = Livewire::test(TwoFactorChallenge::class, ['userId' => $usuario->getKey()]);

    $componente->assertSeeHtml('wire:model="code"');

    $componente->call('toggleRecoveryCode');

    $componente->assertSeeHtml('wire:model="recoveryCode"');
    $componente->assertSee(__('base-tenant::auth.2fa.use_authentication_code'));
});

test('el reto de dos factores conserva la vuelta al acceso', function () {
    $usuario = $this->createUser();

    Livewire::test(TwoFactorChallenge::class, ['userId' => $usuario->getKey()])
        ->assertSee(__('base-tenant::auth.2fa.back_to_login'))
        ->assertSeeHtml(route('base-tenant.login'));
});

/**
 * La pantalla de dos factores vive en la página de perfil entre secciones de
 * dos columnas. Mientras fuese un bloque a todo el ancho, la página se leía
 * partida.
 */
test('el segundo factor usa el patrón de dos columnas del resto del perfil', function () {
    $ruta = dirname(__DIR__, 2).'/resources/views/livewire/two-factor-authentication.blade.php';

    expect((string) file_get_contents($ruta))->toContain('md:grid-cols-3');
});

/**
 * Esquema mínimo para poder ejercitar los cuatro tipos de campo que la pantalla
 * de ajustes sabe pintar.
 */
class AjustesDePrueba extends SettingsSchema
{
    public string $brand_name = 'Base';

    public int $max_items = 10;

    public bool $enabled = false;

    public array $payload = [];

    public static function group(): string
    {
        return 'pruebas';
    }
}

/**
 * La pantalla de reto no declaraba layout, así que Livewire caía en el de la
 * aplicación —que este paquete no trae— y la ruta a la que redirige el acceso
 * cuando hay segundo factor respondía con un 500: el inicio de sesión con dos
 * factores no tenía salida.
 */
test('la pantalla de reto de dos factores se sirve por HTTP', function () {
    $usuario = $this->createUser();

    session(['2fa.user_id' => $usuario->getKey()]);

    $this->get(route('base-tenant.two-factor.challenge'))
        ->assertOk()
        ->assertSee(__('base-tenant::auth.2fa.verification_title'));
});
