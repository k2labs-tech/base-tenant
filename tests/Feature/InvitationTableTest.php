<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\InvitationManager;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Tests\Fixtures\User as HostUser;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

function invitar(Account $cuenta, string $correo, array $atributos = []): UserInvite
{
    return Tenant::runFor($cuenta, fn (): UserInvite => UserInvite::create([
        'email' => $correo,
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
        ...$atributos,
    ]));
}

test('la búsqueda encuentra invitaciones por correo', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');
    invitar($cuenta, 'bruno@otrositio.test');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->set('search', 'otrositio')
        ->assertSee('bruno@otrositio.test')
        ->assertDontSee('ana@ejemplo.test');
});

test('ordenar invitaciones por correo funciona en los dos sentidos', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'carla@ejemplo.test');
    invitar($cuenta, 'ana@ejemplo.test');
    invitar($cuenta, 'bruno@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(InvitationManager::class)->call('sort', 'email');

    $componente->assertSeeHtmlInOrder(['ana@ejemplo.test', 'bruno@ejemplo.test', 'carla@ejemplo.test']);

    $componente->call('sort', 'email')
        ->assertSeeHtmlInOrder(['carla@ejemplo.test', 'bruno@ejemplo.test', 'ana@ejemplo.test']);
});

test('una columna no declarada no ordena las invitaciones', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::withQueryParams(['sortBy' => 'token'])
        ->test(InvitationManager::class)
        ->assertOk()
        ->assertSee('ana@ejemplo.test');
});

/**
 * La pantalla solo enseñaba las pendientes. Con el filtro de estado tiene que
 * poder enseñar las tres, o «caducada» y «aceptada» no filtrarían nada.
 */
test('el filtro de estado separa pendientes, caducadas y aceptadas', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'pendiente@ejemplo.test');
    invitar($cuenta, 'caducada@ejemplo.test', ['expires_at' => now()->subDay()]);
    invitar($cuenta, 'aceptada@ejemplo.test', ['accepted_at' => now()->subHour()]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(InvitationManager::class);

    // Sin filtro se ven las tres.
    $componente->assertSee('pendiente@ejemplo.test')
        ->assertSee('caducada@ejemplo.test')
        ->assertSee('aceptada@ejemplo.test');

    $componente->set('filterStatus', 'pending')
        ->assertSee('pendiente@ejemplo.test')
        ->assertDontSee('caducada@ejemplo.test')
        ->assertDontSee('aceptada@ejemplo.test');

    $componente->set('filterStatus', 'expired')
        ->assertSee('caducada@ejemplo.test')
        ->assertDontSee('pendiente@ejemplo.test')
        ->assertDontSee('aceptada@ejemplo.test');

    $componente->set('filterStatus', 'accepted')
        ->assertSee('aceptada@ejemplo.test')
        ->assertDontSee('pendiente@ejemplo.test')
        ->assertDontSee('caducada@ejemplo.test');
});

/**
 * Una invitación aceptada suele estar también pasada de fecha. Si el estado se
 * decide solo por `expires_at`, se anuncia como caducada algo que sí se usó.
 */
test('una invitación aceptada no se anuncia como caducada', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'aceptada@ejemplo.test', [
        'expires_at' => now()->subWeek(),
        'accepted_at' => now()->subDay(),
    ]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->assertSeeHtml('data-invite-status="accepted"')
        ->assertDontSeeHtml('data-invite-status="expired"');
});

test('una invitación a punto de caducar se marca como urgente', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'justo@ejemplo.test', ['expires_at' => now()->addHours(12)]);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->assertSeeHtml('data-invite-status="expiring"');
});

test('la tabla de invitaciones pagina', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    foreach (range(1, 12) as $indice) {
        invitar($cuenta, "persona{$indice}@ejemplo.test");
    }

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::withQueryParams(['perPage' => '10'])->test(InvitationManager::class);

    expect($componente->viewData('invites')->perPage())->toBe(10)
        ->and($componente->viewData('invites')->total())->toBe(12)
        ->and($componente->viewData('invites')->count())->toBe(10);
});

test('el formulario de invitación sigue enviando', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');
    $rol = Tenant::runFor($cuenta, fn () => Role::query()->assignable()->nonSystem()->first());

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->set('email', 'nueva@ejemplo.test')
        ->set('selectedRole', $rol->id)
        ->call('sendInvite')
        ->assertHasNoErrors();

    expect(UserInvite::query()->forAccount($cuenta)->where('email', 'nueva@ejemplo.test')->exists())->toBeTrue();
});

test('limpiar filtros también suelta el estado', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->set('search', 'ana')
        ->set('filterStatus', 'pending')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterStatus', '')
        ->assertViewHas('hasActiveFilters', false);
});

test('el vacío por búsqueda de invitaciones no usa la copia del vacío inicial', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->set('search', 'no-existe-nadie-asi')
        ->assertSee(__('base-tenant::common.empty_search'))
        ->assertDontSee(__('base-tenant::invitations.empty_description'));
});

test('una página de invitaciones fuera de rango no se confunde con una tabla vacía', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->call('gotoPage', 5)
        ->assertOk()
        ->assertDontSee(__('base-tenant::common.empty_title'))
        ->assertDontSee(__('base-tenant::invitations.empty_description'));
});

/**
 * Una cabecera con más columnas que celdas pinta una tabla torcida sin que nada
 * falle. Se cuentan las dos y se comparan.
 */
test('la tabla de invitaciones pinta tantas celdas como columnas declara', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(InvitationManager::class)->html();

    preg_match('/<thead.*?<\/thead>/s', $html, $cabecera);
    // Anclado al cuerpo real: el primer `<tbody` de la tabla es el del
    // esqueleto de carga, y medirlo aquí dejaría sin comprobar la fila que se
    // ve el resto del tiempo.
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($cabecera)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    // Con el espacio: `<th` sin él también cuenta la etiqueta `<thead`.
    expect(substr_count($cabecera[0], '<th '))->toBe(6)
        ->and(substr_count($primeraFila[0], '<td '))->toBe(6);
});

/**
 * El esqueleto se pinta con la tabla ya montada, así que una fila con menos
 * celdas que la real endereza la tabla al desaparecer y la tuerce al aparecer.
 * Se cuentan las dos filas y se comparan.
 */
test('el esqueleto de carga de invitaciones pinta tantas celdas como una fila real', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'ana@ejemplo.test');

    $this->actingAsTenant($admin, $cuenta);

    $html = Livewire::test(InvitationManager::class)->html();

    preg_match('/<tbody wire:loading\.delay.*?<tr.*?<\/tr>/s', $html, $esqueleto);
    preg_match('/<tbody wire:loading\.remove.*?<tr.*?<\/tr>/s', $html, $primeraFila);

    expect($esqueleto)->not->toBeEmpty()->and($primeraFila)->not->toBeEmpty();

    expect(substr_count($esqueleto[0], '<td '))
        ->toBe(substr_count($primeraFila[0], '<td '));
});

/**
 * Quién invitó sale de una relación al modelo de usuario configurado, que en
 * una instalación real no es el del paquete. Si no casa, la celda cae al texto
 * de «enviada por el sistema» y nadie se entera de que hubo un fallo.
 */
test('la celda de quién invitó resuelve contra el modelo configurado', function () {
    config()->set('base-tenant.models.user', HostUser::class);

    $cuenta = $this->createAccount();

    $anfitriona = HostUser::create([
        'name' => 'Ana Anfitriona',
        'email' => 'ana.anfitriona@example.com',
        'password' => bcrypt('password'),
        'account_id' => $cuenta->getKey(),
    ]);
    $anfitriona->accounts()->syncWithoutDetaching([$cuenta->getKey()]);

    invitar($cuenta, 'invitada@ejemplo.test', ['invited_by' => $anfitriona->getKey()]);

    $admin = $this->createUser($cuenta, 'customer-admin');
    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(InvitationManager::class)
        ->assertSee(__('base-tenant::invitations.invited_by_name', ['name' => 'Ana Anfitriona']))
        ->assertDontSee(__('base-tenant::invitations.invited_by_unknown'));
});

/**
 * Una invitación caducada no avisa a nadie por su cuenta: ni se usó ni volverá
 * a usarse. Decirlo en la cabecera es lo que la hace accionable.
 */
test('la tira de contexto avisa de las invitaciones caducadas', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    invitar($cuenta, 'viva@ejemplo.test');
    invitar($cuenta, 'caducada@ejemplo.test', ['expires_at' => now()->subDay()]);

    $this->actingAsTenant($admin, $cuenta);

    $componente = Livewire::test(InvitationManager::class);

    expect($componente->viewData('summary'))->toBe(['total' => 2, 'expired' => 1]);

    $componente->assertSee(trans_choice('base-tenant::invitations.expired_summary', 1, ['count' => 1]));
});
