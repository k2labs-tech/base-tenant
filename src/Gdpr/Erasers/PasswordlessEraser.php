<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\MagicLink;
use Base\Tenant\Models\Passkey;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sign-in links carry an address and a browser fingerprint; a passkey is a
 * credential whose id can never be registered again while the row lives.
 */
class PasswordlessEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        $id = $user->getAuthIdentifier();
        $email = $user->getAttribute('email');

        MagicLink::query()
            ->where(fn ($query) => $query
                ->where('user_id', $id)
                ->when($email, fn ($query) => $query->orWhere('email', $email)))
            ->delete();

        Passkey::query()->where('user_id', $id)->delete();
    }
}
