<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Services\PermissionRegistry;
use Illuminate\Support\Collection;

trait HasExtensibleRoles
{
    /**
     * Every role declared in configuration, across all groups.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function getAllConfiguredRoles(): Collection
    {
        $config = config('base-tenant.roles', []);

        return collect([
            ...$config['system'] ?? [],
            ...$config['customer'] ?? [],
            ...$config['custom'] ?? [],
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function getSystemRoles(): Collection
    {
        return collect(config('base-tenant.roles.system', []));
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function getCustomerRoles(): Collection
    {
        return collect(config('base-tenant.roles.customer', []));
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function getCustomRoles(): Collection
    {
        return collect(config('base-tenant.roles.custom', []));
    }

    /**
     * Write the configured permissions and roles to the database.
     */
    public static function syncRolesToDatabase(): void
    {
        PermissionRegistry::sync();
    }

    public static function roleExists(string $key): bool
    {
        return static::getAllConfiguredRoles()->pluck('key')->contains($key);
    }

    /** @return array<string, mixed>|null */
    public static function getRoleByKey(string $key): ?array
    {
        return static::getAllConfiguredRoles()->firstWhere('key', $key);
    }
}
