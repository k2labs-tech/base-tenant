<?php

declare(strict_types=1);

namespace Base\Tenant\Onboarding\Checks;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\UserInvite;

/**
 * Somebody besides the owner has been brought in.
 */
class TeamInvited
{
    public function __invoke(Account $account): bool
    {
        if ($account->users()->count() > 1) {
            return true;
        }

        // A pending invitation counts: the work of inviting is done, and
        // marking the step incomplete until somebody else accepts makes the
        // checklist depend on a third party.
        return Tenant::runFor($account, fn (): bool => UserInvite::query()->exists());
    }
}
