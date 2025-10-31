<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Models\Role;
use Illuminate\Support\Collection;

trait HasExtensibleRoles
{
    /**
     * Get all roles defined in the configuration.
     */
    public static function getAllConfiguredRoles(): Collection
    {
        $config = config('base-tenant.roles', []);

        $roles = collect([
            ...$config['system'] ?? [],
            ...$config['customer'] ?? [],
            ...$config['custom'] ?? [],
        ]);

        return $roles;
    }

    /**
     * Get system roles from configuration.
     */
    public static function getSystemRoles(): Collection
    {
        return collect(config('base-tenant.roles.system', []));
    }

    /**
     * Get customer roles from configuration.
     */
    public static function getCustomerRoles(): Collection
    {
        return collect(config('base-tenant.roles.customer', []));
    }

    /**
     * Get custom roles defined by the application.
     */
    public static function getCustomRoles(): Collection
    {
        return collect(config('base-tenant.roles.custom', []));
    }

    /**
     * Sync configured roles to database.
     */
    public static function syncRolesToDatabase(): void
    {
        $configuredRoles = static::getAllConfiguredRoles();

        foreach ($configuredRoles as $roleData) {
            Role::query()->updateOrCreate(
                ['key' => $roleData['key']],
                [
                    'name' => $roleData['name'],
                    'is_system' => $roleData['is_system'],
                ]
            );
        }
    }

    /**
     * Check if a role key is defined in configuration.
     */
    public static function roleExists(string $key): bool
    {
        return static::getAllConfiguredRoles()
            ->pluck('key')
            ->contains($key);
    }

    /**
     * Get role by key from configuration.
     */
    public static function getRoleByKey(string $key): ?array
    {
        return static::getAllConfiguredRoles()
            ->firstWhere('key', $key);
    }
}
