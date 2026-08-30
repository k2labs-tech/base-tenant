<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\Traits\HasRoles;

/**
 * Roles and permissions for a user, scoped to the account in context.
 *
 * Everything resolves from the database through spatie/laravel-permission, so
 * the same answer comes back in a web request, a queued job, an Artisan
 * command and an API call.
 */
trait HasRolesAndPermissions
{
    use HasRoles;

    /**
     * Platform staff bypass every check through the gate.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * spatie/laravel-permission infers the guard by matching the class against
     * `config('auth.providers.*.model')`. A host application that points its
     * provider at its own User subclass leaves this model unmatched, and role
     * assignment then fails with an empty guard. Fall back to the configured
     * guard rather than to nothing.
     */
    protected function getGuardNames(): Collection
    {
        $names = Guard::getNames($this);

        return $names->isNotEmpty()
            ? $names
            : collect([$this->getDefaultGuardName()]);
    }

    protected function getDefaultGuardName(): string
    {
        return config('base-tenant.permissions_guard', 'web');
    }

    /**
     * Check a permission without blowing up when it is not in the catalogue,
     * which is what policies want.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        try {
            return $this->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function belongsToAccount(Account|string|null $account): bool
    {
        if ($account === null) {
            return false;
        }

        $accountId = $account instanceof Account ? $account->getKey() : $account;

        if ($this->account_id === $accountId) {
            return true;
        }

        return $this->accounts()->whereKey($accountId)->exists();
    }

    /**
     * Assign a role by its key within the account in context.
     */
    public function addRole(?string $role = null): bool
    {
        if (! $role) {
            return false;
        }

        $roleClass = config('base-tenant.models.role', Role::class);

        $model = $roleClass::query()
            ->where('name', $role)
            ->assignable()
            ->first();

        if (! $model) {
            return false;
        }

        $this->assignRole($model);

        return true;
    }

    public function authorizeRoles(array|string $roles): bool
    {
        abort_unless($this->hasAnyRole($roles), 403, __('base-tenant::auth.unauthorized'));

        return true;
    }

    /**
     * Roles this user holds in a given account, regardless of the one in
     * context.
     *
     * @return EloquentCollection<int, Role>
     */
    public function rolesForAccount(Account|string $account): EloquentCollection
    {
        return Tenant::runFor($account, fn (): EloquentCollection => $this->roles()->get());
    }

    /**
     * Check a permission inside a specific account.
     */
    public function hasPermissionInAccount(Account|string $account, string $permission): bool
    {
        return Tenant::runFor($account, function () use ($permission): bool {
            $this->unsetRelation('roles')->unsetRelation('permissions');

            return $this->isSuperAdmin() || $this->hasPermissionTo($permission);
        });
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Kept so existing calls keep working. Roles no longer live in the
     * session, so this only drops the in-memory relations to force a reload.
     *
     * @deprecated Roles resolve from the database on every check.
     */
    public function storeRolesSession(): void
    {
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }
}
