<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\SocialAccount;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A linked provider holds a refresh token: a standing grant to act as the
 * person at the provider, which must not outlive them here.
 */
class SocialAccountEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        SocialAccount::query()->where('user_id', $user->getAuthIdentifier())->delete();
    }
}
