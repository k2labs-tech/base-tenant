<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Exceptions\DomainNotAllowedException;
use Base\Tenant\Exceptions\InvitationException;
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
     * Session key holding the token of an invitation opened before sign-in.
     */
    public const PENDING_TOKEN_KEY = 'pending_invite_token';

    public static function remember(UserInvite $invite): void
    {
        session([self::PENDING_TOKEN_KEY => $invite->token]);
    }

    /**
     * Accept the invitation remembered in the session, if the person who just
     * signed in is the one it was sent to. Silent on every refusal: the person
     * asked to sign in, not to hear about an invitation, and the link in their
     * inbox still works.
     */
    public static function acceptPending(User $user): ?UserInvite
    {
        $invite = static::remembered();

        if ($invite === null) {
            session()->forget(self::PENDING_TOKEN_KEY);

            return null;
        }

        try {
            static::accept($invite, $user);
        } catch (InvitationException|DomainNotAllowedException) {
            // Left in the session: it may still be for the next person who
            // signs in on this browser.
            return null;
        }

        session()->forget(self::PENDING_TOKEN_KEY);

        return $invite;
    }

    /**
     * The pending invitation remembered in the session, if it is still one.
     */
    public static function remembered(): ?UserInvite
    {
        $token = session()->get(self::PENDING_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $invite = UserInvite::query()->acrossAccounts()->where('token', $token)->first();

        if ($invite === null || ! $invite->isPending()) {
            return null;
        }

        return $invite;
    }

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
        static::ensureEmailAllowed($email, Account::find($accountId));

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

    /**
     * @throws DomainNotAllowedException when the account has since restricted email domains
     */
    public static function resend(UserInvite $invite): void
    {
        // The rule may have been tightened after the invitation went out. A
        // pending invite to an address that is no longer allowed must not be
        // refreshed and mailed again around it.
        static::ensureEmailAllowed($invite->email, $invite->account);

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

    /**
     * @throws InvitationException when the invitation was sent to another address
     * @throws DomainNotAllowedException when the account no longer allows the address
     */
    public static function accept(UserInvite $invite, User $user): void
    {
        // The invitation is for the address it was mailed to. Whoever happens
        // to be signed in when the link is opened is not necessarily that
        // person, and attaching them would let anybody with the link into the
        // account under an address the administrator never approved.
        if (mb_strtolower(trim($user->email)) !== mb_strtolower(trim($invite->email))) {
            throw InvitationException::forAnotherAddress();
        }

        static::ensureEmailAllowed($user->email, $invite->account);

        $invite->update(['accepted_at' => now()]);

        $user->accounts()->syncWithoutDetaching([$invite->account_id]);

        if (! $user->account_id) {
            $user->update(['account_id' => $invite->account_id]);
        }

        if ($invite->account) {
            Security::startTwoFactorClockFor($user, $invite->account);
        }

        if (! $invite->role_id) {
            return;
        }

        Tenant::runFor($invite->account_id, function () use ($user, $invite): void {
            $user->assignRole($invite->role);
        });
    }

    /**
     * @throws DomainNotAllowedException
     */
    protected static function ensureEmailAllowed(string $email, ?Account $account): void
    {
        if ($account === null) {
            return;
        }

        if (Security::allowsEmail($email, $account)) {
            return;
        }

        throw DomainNotAllowedException::for(
            $email,
            Security::for($account)->allowedEmailDomains
        );
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
