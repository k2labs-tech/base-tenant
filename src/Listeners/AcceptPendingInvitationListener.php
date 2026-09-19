<?php

declare(strict_types=1);

namespace Base\Tenant\Listeners;

use Base\Tenant\Models\User;
use Base\Tenant\Services\InvitationService;
use Illuminate\Auth\Events\Login;

/**
 * Finish an invitation the person opened before they were signed in.
 *
 * On the login event and not in the login form: a sign-in can come from a
 * password, a magic link, a passkey, a social provider or the second-factor
 * challenge, and an invitation that only completed for one of them would be
 * a support ticket for the other four.
 */
class AcceptPendingInvitationListener
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        InvitationService::acceptPending($event->user);
    }
}
