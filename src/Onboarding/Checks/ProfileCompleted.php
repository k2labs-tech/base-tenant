<?php

declare(strict_types=1);

namespace Base\Tenant\Onboarding\Checks;

use Base\Tenant\Models\Account;

/**
 * The owner has filled in more than the registration form asked for.
 */
class ProfileCompleted
{
    public function __invoke(Account $account): bool
    {
        $owner = $account->owner;

        return $owner !== null
            && filled($owner->name)
            && $owner->email_verified_at !== null;
    }
}
