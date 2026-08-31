<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Exceptions\DomainNotAllowedException;
use Base\Tenant\Facades\Security;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Notifications\InviteUserNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * @throws DomainNotAllowedException when the account restricts email domains
     */
    public static function send(
        string $email,
        string $accountId,
        string $roleId,
        User $inviter,
    ): UserInvite {
        // The domain rule is enforced here rather than in the screen, because
        // an invitation can also be sent from a command or a job and the point
        // of the rule is that nobody gets into the account around it.
        $account = Account::find($accountId);

        if ($account && ! Security::allowsEmail($email, $account)) {
            throw DomainNotAllowedException::for(
                $email,
                Security::for($account)->allowedEmailDomains
            );
        }

        UserInvite::query()
            ->forAccount($accountId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invite = UserInvite::create([
            'email' => $email,
            'account_id' => $accountId,
            'role_id' => $roleId,
            'invited_by' => $inviter->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(
                config('base-tenant.invitations.expires_in_days', 7)
            ),
        ]);

        Notification::route('mail', $email)
            ->notify(new InviteUserNotification(
                $invite,
                $inviter->name,
                $invite->account->name,
            ));

        return $invite;
    }

    public static function resend(UserInvite $invite): void
    {
        $invite->update([
            'expires_at' => now()->addDays(
                config('base-tenant.invitations.expires_in_days', 7)
            ),
        ]);

        Notification::route('mail', $invite->email)
            ->notify(new InviteUserNotification(
                $invite,
                $invite->invitedBy->name ?? 'Admin',
                $invite->account->name,
            ));
    }

    public static function accept(UserInvite $invite, User $user): void
    {
        $invite->update(['accepted_at' => now()]);

        $user->accounts()->syncWithoutDetaching([$invite->account_id]);

        if (! $user->account_id) {
            $user->update(['account_id' => $invite->account_id]);
        }

        if (! $invite->role_id) {
            return;
        }

        Tenant::runFor($invite->account_id, function () use ($user, $invite): void {
            $user->assignRole($invite->role);
        });
    }

    public static function revoke(UserInvite $invite): void
    {
        $invite->delete();
    }

    public static function getPendingForAccount(string $accountId)
    {
        return UserInvite::pending()
            ->forAccount($accountId)
            ->with(['role', 'invitedBy'])
            ->latest()
            ->get();
    }
}
