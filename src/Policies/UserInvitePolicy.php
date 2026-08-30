<?php

declare(strict_types=1);

namespace Base\Tenant\Policies;

use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;

class UserInvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invitations.view');
    }

    public function view(User $user, UserInvite $invite): bool
    {
        return $user->hasPermission('invitations.view') && $user->belongsToAccount($invite->account_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invitations.create');
    }

    public function delete(User $user, UserInvite $invite): bool
    {
        return $user->hasPermission('invitations.revoke') && $user->belongsToAccount($invite->account_id);
    }

    public function resend(User $user, UserInvite $invite): bool
    {
        return $user->hasPermission('invitations.create') && $user->belongsToAccount($invite->account_id);
    }
}
