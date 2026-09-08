<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Exceptions\DomainNotAllowedException;
use Base\Tenant\Exceptions\InvitationException;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationAcceptController
{
    public function __invoke(Request $request, string $token)
    {
        // Public route: the invite is looked up outside any tenant context.
        $invite = UserInvite::query()->acrossAccounts()->where('token', $token)->firstOrFail();

        if ($invite->isExpired()) {
            return redirect()->route('base-tenant.login')
                ->with('status', __('base-tenant::invitations.expired'));
        }

        if ($invite->isAccepted()) {
            return redirect()->route('base-tenant.login')
                ->with('status', __('base-tenant::invitations.already_accepted'));
        }

        if (Auth::check()) {
            try {
                InvitationService::accept($invite, Auth::user());
            } catch (InvitationException|DomainNotAllowedException $exception) {
                // Not remembered: signing out flushes the session, so the
                // message tells the person to open the link again instead.
                return redirect()->route('base-tenant.dashboard')
                    ->with('error', $exception->getMessage());
            }

            return redirect()->route('base-tenant.dashboard')
                ->with('status', __('base-tenant::invitations.accepted'));
        }

        // Remembered for the login listener, which accepts it once the person
        // who signs in turns out to be the one it was sent to.
        InvitationService::remember($invite);

        $userClass = config('base-tenant.models.user');
        $existingUser = $userClass::where('email', $invite->email)->first();

        if ($existingUser) {
            return redirect()->route('base-tenant.login')
                ->with('status', __('base-tenant::invitations.login_to_accept'));
        }

        return redirect()->route('base-tenant.register')
            ->with('status', __('base-tenant::invitations.register_to_accept'));
    }
}
