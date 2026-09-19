<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The account memberships. The pivot has no foreign key to cascade from, so
 * a purge that forgot it would leave every account counting a member who is
 * not there.
 */
class MembershipEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $user->accounts()->detach();
    }
}
