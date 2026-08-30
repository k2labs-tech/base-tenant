<?php

declare(strict_types=1);

namespace Base\Tenant\Policies;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view') && $this->isReachable($role);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    /**
     * Roles shipped by the product are read-only for tenants; an account can
     * only edit the roles it defined itself.
     */
    public function update(User $user, Role $role): bool
    {
        if (! $user->hasPermission('roles.update')) {
            return false;
        }

        if ($role->isGlobal()) {
            return $user->isSuperAdmin();
        }

        return $role->account_id === Tenant::currentId();
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $this->update($user, $role);
    }

    protected function isReachable(Role $role): bool
    {
        return $role->isGlobal() || $role->account_id === Tenant::currentId();
    }
}
