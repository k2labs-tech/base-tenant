<?php

declare(strict_types=1);

use Base\Tenant\Database\Seeders\AdminUserSeeder;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;

beforeEach(function () {
    $this->syncPermissions();
});

/**
 * @return array<int, string>
 */
function rolesDeSistema(): array
{
    return collect(config('base-tenant.roles.system', []))->pluck('key')->all();
}

test('el administrador de plataforma recibe todos los roles de sistema', function () {
    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('base-tenant.admin.email'))->first();

    expect($admin)->not->toBeNull()
        ->and($admin->is_admin)->toBeTrue();

    Tenant::runWithout(function () use ($admin) {
        expect($admin->roles->pluck('name')->sort()->values()->all())
            ->toBe(collect(rolesDeSistema())->sort()->values()->all());
    });
});

/**
 * El caso que dejaba fuera al administrador de siempre: una versión añade un
 * rol de sistema y solo lo reciben las instalaciones creadas después.
 */
test('un administrador que ya existía recibe los roles nuevos', function () {
    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('base-tenant.admin.email'))->first();

    // Se le quitan todos, como si esa versión no los hubiera tenido nunca.
    Tenant::runWithout(function () use ($admin) {
        $admin->roles()->detach();
        $admin->unsetRelation('roles');

        expect($admin->roles)->toBeEmpty();
    });

    (new AdminUserSeeder)->run();

    Tenant::runWithout(function () use ($admin) {
        $admin->unsetRelation('roles');

        expect($admin->roles->pluck('name')->all())->toContain('administrator');
    });
});

test('sembrar dos veces no duplica ni el usuario ni sus roles', function () {
    (new AdminUserSeeder)->run();
    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('base-tenant.admin.email'))->first();

    expect(User::where('email', config('base-tenant.admin.email'))->count())->toBe(1);

    Tenant::runWithout(function () use ($admin) {
        $admin->unsetRelation('roles');

        expect($admin->roles)->toHaveCount(count(rolesDeSistema()));
    });
});

/**
 * Los roles del personal viven en el equipo de sistema, que es donde el
 * resolver mira cuando no hay ninguna cuenta en contexto. Si aterrizaran en
 * otro sitio, el administrador tendría los roles en la tabla y ninguno
 * resolvería.
 */
test('los roles del administrador resuelven sin cuenta en contexto', function () {
    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('base-tenant.admin.email'))->first();

    Tenant::forget();

    $admin->unsetRelation('roles')->unsetRelation('permissions');

    expect($admin->hasRole('administrator'))->toBeTrue();
});

/**
 * `administrator` lleva `*`, así que tiene que cubrir cada permiso declarado
 * -- incluidos los que añada una versión posterior. Se comprueba sin el atajo
 * de superadmin, que responde que sí a todo y taparía el fallo.
 */
test('el rol de administrador cubre todos los permisos declarados', function () {
    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('base-tenant.admin.email'))->first();

    // Sin el atajo: lo que se comprueba es el rol, no la bandera.
    $admin->forceFill(['is_admin' => false])->save();

    Tenant::runWithout(function () use ($admin) {
        $admin->unsetRelation('roles')->unsetRelation('permissions');

        $faltan = [];

        foreach (collect(config('base-tenant.permissions'))->flatten() as $permiso) {
            if (! $admin->hasPermission($permiso)) {
                $faltan[] = $permiso;
            }
        }

        expect($faltan)->toBe([], 'El administrador no alcanza: '.implode(', ', $faltan));
    });
});
