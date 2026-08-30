<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the permission catalogue and the global role definitions in the
 * database aligned with configuration.
 */
class PermissionRegistry
{
    /**
     * @return array{permissions: int, roles: int}
     */
    public static function sync(): array
    {
        $permissions = static::syncPermissions();
        $roles = static::syncRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['permissions' => $permissions, 'roles' => $roles];
    }

    protected static function syncPermissions(): int
    {
        $permissionClass = config('base-tenant.models.permission', Permission::class);
        $count = 0;

        foreach (config('base-tenant.permissions', []) as $group => $names) {
            foreach ($names as $name) {
                $permissionClass::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => static::guard()],
                    ['group' => (string) $group]
                );

                $count++;
            }
        }

        return $count;
    }

    protected static function syncRoles(): int
    {
        $roleClass = config('base-tenant.models.role', Role::class);
        $count = 0;

        foreach (static::configuredRoles() as $definition) {
            $role = $roleClass::query()->updateOrCreate(
                [
                    'name' => $definition['key'],
                    'guard_name' => static::guard(),
                    'account_id' => null,
                ],
                [
                    'display_name' => $definition['name'] ?? $definition['key'],
                    'is_system' => (bool) ($definition['is_system'] ?? false),
                ]
            );

            $role->syncPermissions(static::resolvePermissions($definition['permissions'] ?? []));

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, string>|string  $declared
     * @return array<int, string>
     */
    protected static function resolvePermissions(array|string $declared): array
    {
        if ($declared === '*') {
            return Permission::allConfiguredNames();
        }

        if (! is_array($declared)) {
            return [];
        }

        $available = Permission::allConfiguredNames();
        $resolved = [];

        foreach ($declared as $entry) {
            if (! str_contains($entry, '*')) {
                $resolved[] = $entry;

                continue;
            }

            $pattern = '/^'.str_replace('\*', '.*', preg_quote($entry, '/')).'$/';
            $resolved = [...$resolved, ...preg_grep($pattern, $available)];
        }

        return array_values(array_unique(array_intersect($resolved, $available)));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected static function configuredRoles(): Collection
    {
        $config = config('base-tenant.roles', []);

        return collect([
            ...$config['system'] ?? [],
            ...$config['customer'] ?? [],
            ...$config['custom'] ?? [],
        ]);
    }

    protected static function guard(): string
    {
        return config('base-tenant.permissions_guard', 'web');
    }
}
