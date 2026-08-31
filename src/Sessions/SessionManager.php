<?php

declare(strict_types=1);

namespace Base\Tenant\Sessions;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Services\ActivityLogService;
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
     * Record this request against the user's session, creating the row the
     * first time it is seen.
     */
    public function touch(Request $request, User $user): ?UserSession
    {
        if (! $request->hasSession()) {
            return null;
        }

        $fingerprint = UserSession::fingerprint($request->session()->getId());
        $agent = $request->userAgent();
        $parsed = DeviceParser::parse($agent);

        $session = UserSession::query()->firstOrNew(['session_id' => $fingerprint]);

        // Never resurrect a revoked row by touching it. Whoever revoked it
        // decided this session is over, and an update that clears the flag
        // would silently undo that.
        if ($session->exists && $session->isRevoked()) {
            return $session;
        }

        $session->fill([
            'user_id' => $user->getKey(),
            'account_id' => Tenant::currentId(),
            'ip_address' => $request->ip(),
            'user_agent' => $agent,
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'last_active_at' => now(),
        ])->save();

        return $session;
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
