<?php

declare(strict_types=1);

use Base\Tenant\Livewire\NavigationManager;
use Base\Tenant\Livewire\Notifications\Index as NotificationsIndex;
use Base\Tenant\Livewire\RoleManager;
use Base\Tenant\Models\Role;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * `wire:confirm` levanta el diálogo nativo del navegador: su botón sale en el
 * idioma del navegador y no en el de la aplicación, no se puede dar estilo, no
 * dice qué se va a borrar más allá de la frase suelta que se le pase, y hay
 * navegadores que dejan al usuario silenciarlo para el resto de la sesión —con
 * lo que la siguiente pulsación borra sin preguntar.
 *
 * Las tres confirmaciones destructivas del paquete pasan a `flux:modal`, que
 * nombra el objeto y pinta el botón en rojo.
 */
beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
});

/**
 * La guarda: es una regla fácil de olvidar y cuesta un `grep`.
 */
test('ninguna vista usa el diálogo nativo del navegador', function () {
    $raiz = dirname(__DIR__, 2).'/resources/views';

    $culpables = [];

    foreach (File::allFiles($raiz) as $fichero) {
        if (! str_ends_with($fichero->getFilename(), '.blade.php')) {
            continue;
        }

        if (str_contains(File::get($fichero->getPathname()), 'wire:confirm')) {
            $culpables[] = $fichero->getRelativePathname();
        }
    }

    sort($culpables);

    expect($culpables)->toBe([]);
});

test('borrar un rol pasa por un modal que lo nombra', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $rol = $this->createRole($cuenta, 'auditor');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(RoleManager::class)
        ->call('confirmDelete', (string) $rol->getKey());

    // El modal nombra el rol concreto, no «este elemento».
    $componente->assertSee('auditor');
    $componente->assertSet('showDeleteModal', true);

    // `variant` lo consume Flux como prop y nunca llega al HTML: lo que sí
    // llega es el color que ese variant pinta.
    $componente->assertSeeHtml('bg-red-500');

    expect(Role::query()->whereKey($rol->getKey())->exists())->toBeTrue();

    $componente->call('deleteRole', (string) $rol->getKey());

    expect(Role::query()->whereKey($rol->getKey())->exists())->toBeFalse();
    $componente->assertSet('showDeleteModal', false);
});

test('abrir el modal de borrado no borra nada por sí solo', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $rol = $this->createRole($cuenta, 'auditor');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(RoleManager::class)->call('confirmDelete', (string) $rol->getKey());

    expect(Role::query()->whereKey($rol->getKey())->exists())->toBeTrue();
});

/**
 * Un rol de sistema no lo borra nadie. Ojo con probarlo con un administrador
 * del sistema: el `Gate::before` del proveedor le concede todo antes de que
 * `RolePolicy::delete()` llegue a mirar `is_system`, así que para ese usuario
 * la regla no existe. Quien la nota es un cliente, y para que la vea el rol
 * tiene que ser de su cuenta: los globales ni siquiera los encuentra.
 */
test('un rol de sistema no llega ni a preguntar', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $sistema = Role::create([
        'name' => 'interno',
        'display_name' => 'Interno',
        'guard_name' => 'web',
        'account_id' => $cuenta->getKey(),
        'is_system' => true,
    ]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(RoleManager::class)
        ->call('confirmDelete', (string) $sistema->getKey())
        ->assertForbidden();

    expect(Role::query()->whereKey($sistema->getKey())->exists())->toBeTrue();
});

test('restaurar el menú por defecto pasa por un modal que lo nombra', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(NavigationManager::class)
        ->call('confirmRestoreDefaults')
        ->assertSet('showRestoreModal', true);

    $componente->assertSee(__('base-tenant::menus.confirm_restore_title', [
        'menu' => __('base-tenant::menus.names.main'),
    ]));
    $componente->assertSeeHtml('bg-red-500');

    $componente->call('restoreDefaults')->assertSet('showRestoreModal', false);
});

test('borrar notificaciones pasa por un modal que dice cuántas', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $usuario->notify(new AvisoDePrueba);
    $usuario->notify(new AvisoDePrueba);

    $ids = $usuario->notifications()->pluck('id')->all();

    $componente = Livewire::test(NotificationsIndex::class)
        ->set('selectedNotifications', $ids)
        ->call('confirmDeleteSelected')
        ->assertSet('showDeleteModal', true);

    $componente->assertSee(__('base-tenant::notifications.confirm_delete_selected', ['count' => 2]));
    $componente->assertSeeHtml('bg-red-500');

    expect($usuario->notifications()->count())->toBe(2);

    $componente->call('deleteSelected')->assertSet('showDeleteModal', false);

    expect($usuario->fresh()->notifications()->count())->toBe(0);
});

test('la barra de acciones en bloque habla el idioma de la aplicación', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $usuario->notify(new AvisoDePrueba);

    app()->setLocale('es');

    Livewire::test(NotificationsIndex::class)
        ->set('selectedNotifications', $usuario->notifications()->pluck('id')->all())
        ->assertDontSee('Mark as Read')
        ->assertDontSee('notification(s) selected')
        ->assertSee(__('base-tenant::notifications.mark_as_read'));
});

/**
 * La barra de acciones en bloque se tradujo en su día y el resto de la pantalla
 * se quedó en inglés: el título, los filtros y los estados vacíos. Esta guarda
 * cubre lo que quedaba.
 *
 * Los literales que se comprueban están elegidos: los obvios NO sirven porque
 * aparecen dentro de los propios atributos de Livewire y darían por buena una
 * vista sin traducir. `wire:model.live="filterPriority"` contiene «Priority»,
 * `filterReadStatus` contiene «Status» y «Read», y `selectedNotifications`
 * contiene «Notifications». Sólo valen frases que no puedan salir de un nombre
 * de propiedad.
 */
test('la pantalla de notificaciones habla el idioma de la aplicación', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    $usuario->notify(new AvisoDePrueba);

    app()->setLocale('es');

    Livewire::test(NotificationsIndex::class)
        ->assertDontSee('Mark All as Read')
        ->assertDontSee('All Priorities')
        ->assertDontSee('Search notifications...')
        ->assertSee(__('base-tenant::notifications.management_title'))
        ->assertSee(__('base-tenant::notifications.mark_all_as_read'))
        ->assertSee(__('base-tenant::notifications.all_priorities'))
        ->assertSee(__('base-tenant::notifications.filter_priority'));
});

test('el estado vacío de notificaciones habla el idioma de la aplicación', function () {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    // Sin notificaciones: es la única forma de llegar al bloque `@empty`.
    app()->setLocale('es');

    Livewire::test(NotificationsIndex::class)
        ->assertDontSee('No notifications')
        ->assertDontSee("You don't have any notifications matching your filters.")
        ->assertSee(__('base-tenant::notifications.empty_title'))
        ->assertSee(__('base-tenant::notifications.empty_description'));
});

/**
 * Notificación mínima para poder llenar la bandeja en las pruebas.
 */
class AvisoDePrueba extends Notification
{
    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => 'Aviso', 'message' => 'Cuerpo', 'priority' => 'normal'];
    }
}
