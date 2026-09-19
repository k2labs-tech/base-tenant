<?php

declare(strict_types=1);

namespace Base\Tenant\Sessions;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Services\ActivityLogService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The sessions a user has open, and the ability to end them from elsewhere.
 *
 * A lost laptop or a shared computer has no answer if the product cannot list
 * sessions, and "can a user see and revoke their active sessions?" is a fixed
 * line in every security questionnaire.
 */
class SessionManager
{
    /**
     * How long a row is left alone after a touch, when nothing about the
     * request has changed. "Last active" to the minute is all anybody reads;
     * a write per `wire:poll` tick across every open tab is a lot of writes
     * for the same information.
     */
    public const TOUCH_INTERVAL_SECONDS = 60;

    /**
     * Record this request against the user's session, creating the row the
     * first time it is seen. Returns the row -- revoked or not -- so the
     * caller can act on a revocation without a second query.
     */
    public function touch(Request $request, User $user): ?UserSession
    {
        if (! $request->hasSession()) {
            return null;
        }

        $fingerprint = UserSession::fingerprint($request->session()->getId());
        $agent = $request->userAgent();

        $session = UserSession::query()->firstOrNew(['session_id' => $fingerprint]);

        // Never resurrect a revoked row by touching it. Whoever revoked it
        // decided this session is over, and an update that clears the flag
        // would silently undo that.
        if ($session->exists && $session->isRevoked()) {
            return $session;
        }

        if ($session->exists && $this->isFresh($session, $request, $user)) {
            return $session;
        }

        $parsed = DeviceParser::parse($agent);

        $session->fill([
            'user_id' => $user->getKey(),
            'account_id' => Tenant::currentId(),
            'ip_address' => $request->ip(),
            'user_agent' => $agent,
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'last_active_at' => now(),
        ]);

        try {
            $session->save();
        } catch (UniqueConstraintViolationException) {
            // Two first requests of the same session raced and the other one
            // won. Its row is the session's row now.
            return UserSession::query()->where('session_id', $fingerprint)->first();
        }

        return $session;
    }

    /**
     * Touched a moment ago, from the same place, by the same browser, in the
     * same account: nothing to write.
     */
    protected function isFresh(UserSession $session, Request $request, User $user): bool
    {
        if ($session->last_active_at === null) {
            return false;
        }

        if ($session->last_active_at->lt(now()->subSeconds(self::TOUCH_INTERVAL_SECONDS))) {
            return false;
        }

        return $session->user_id === $user->getKey()
            && $session->account_id === Tenant::currentId()
            && $session->ip_address === $request->ip()
            && $session->user_agent === $request->userAgent();
    }

    /**
     * Is the session behind this request one somebody has ended?
     */
    public function isRevoked(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return UserSession::query()
            ->where('session_id', UserSession::fingerprint($request->session()->getId()))
            ->whereNotNull('revoked_at')
            ->exists();
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function forUser(User $user): Collection
    {
        return UserSession::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_active_at')
            ->get();
    }

    public function revoke(UserSession $session): void
    {
        if ($session->isRevoked()) {
            return;
        }

        $session->forceFill(['revoked_at' => now()])->save();

        ActivityLogService::log(
            subject: $session->user,
            action: 'session.revoked',
            newValues: [
                'device' => $session->device,
                'browser' => $session->browser,
                'ip_address' => $session->ip_address,
            ],
        );
    }

    /**
     * End every session of this user except the one making the request.
     *
     * Keeping the current one is not a convenience: signing somebody out of
     * the screen they are using to secure their account is how they stop
     * halfway through.
     */
    public function revokeOthers(User $user, ?string $keepSessionId = null): int
    {
        $keep = UserSession::fingerprint($keepSessionId ?? session()->getId());

        $sessions = UserSession::query()
            ->active()
            ->where('user_id', $user->getKey())
            ->where('session_id', '!=', $keep)
            ->get();

        foreach ($sessions as $session) {
            $this->revoke($session);
        }

        return $sessions->count();
    }

    /**
     * End every session of this user, including the current one. What an
     * administrator uses when somebody leaves the company.
     */
    public function revokeAll(User $user): int
    {
        $sessions = $this->forUser($user);

        foreach ($sessions as $session) {
            $this->revoke($session);
        }

        return $sessions->count();
    }

    /**
     * Drop rows nobody will look at again. Sessions are personal data, so
     * keeping them past their usefulness is a liability rather than an asset.
     */
    public function prune(int $days = 30): int
    {
        return UserSession::query()
            ->where('last_active_at', '<', now()->subDays($days))
            ->delete();
    }
}
