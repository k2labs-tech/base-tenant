<?php

declare(strict_types=1);

namespace Base\Tenant\Policies;

use Base\Tenant\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view') && $this->sharesAccount($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update') && $this->sharesAccount($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        return $user->hasPermission('users.delete') && $this->sharesAccount($user, $target);
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->canImpersonate()
            && $target->canBeImpersonated()
            && ! $user->is($target);
    }

    /**
     * Staff reach every user; everyone else only the ones inside an account
     * they belong to.
     */
    protected function sharesAccount(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($target->account_id && $user->belongsToAccount($target->account_id)) {
            return true;
        }

        return $target->accounts()
            ->whereIn('accounts.id', $user->accounts()->pluck('accounts.id'))
            ->exists();
    }
}
