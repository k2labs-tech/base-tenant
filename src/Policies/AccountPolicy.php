<?php

declare(strict_types=1);

namespace Base\Tenant\Policies;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounts.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->hasPermission('accounts.view') && $user->belongsToAccount($account);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounts.create');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->hasPermission('accounts.update') && $user->belongsToAccount($account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->hasPermission('accounts.delete') && $user->belongsToAccount($account);
    }

    public function billing(User $user, Account $account): bool
    {
        return $user->hasPermission('accounts.billing') && $user->belongsToAccount($account);
    }
}
