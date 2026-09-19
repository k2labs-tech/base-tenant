<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\UserInvite;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Invitations name the person twice: as the address they were sent to, and
 * as the member who sent them. The first is deleted, the second anonymised --
 * the invitation itself still belongs to the account.
 */
class InvitationEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        $email = $user->getAttribute('email');

        if ($email) {
            UserInvite::query()->acrossAccounts()->where('email', $email)->delete();
        }

        UserInvite::query()->acrossAccounts()
            ->where('invited_by', $user->getAuthIdentifier())
            ->update(['invited_by' => null]);
    }
}
