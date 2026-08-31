<?php

declare(strict_types=1);

namespace Base\Tenant\Passwordless;

use Base\Tenant\Models\MagicLink;
use Base\Tenant\Models\User;
use Base\Tenant\Notifications\MagicLinkNotification;
use Base\Tenant\Services\ActivityLogService;
use Base\Tenant\Support\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Signing in with a link instead of a password.
 *
 * The password is the first friction of a sign-up and the first cause of
 * support tickets. The link is not weaker than a password as long as three
 * things hold, and each one has a test: it is single use, it is short lived,
 * and it does not skip the second factor.
 */
class MagicLinkManager
{
    public function enabled(): bool
    {
        return Module::enabled(Module::PASSWORDLESS)
            && (bool) config('base-tenant.passwordless.magic_links.enabled', true);
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('base-tenant.passwordless.magic_links.ttl_minutes', 15));
    }

    /**
     * Send a link, if the address belongs to somebody.
     *
     * Returns nothing on purpose. Telling the caller whether the address was
     * found turns this endpoint into a way of asking "is this person a
     * customer?", which is a disclosure the product has no reason to make.
     * The screen says the same sentence either way.
     */
    public function request(string $email, ?Request $request = null): void
    {
        $email = mb_strtolower(trim($email));

        $model = config('base-tenant.models.user', User::class);
        $user = $model::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $token = Str::random(48);

        MagicLink::create([
            'email' => $email,
            'user_id' => $user->getKey(),
            'token' => MagicLink::fingerprint($token),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        $user->notify(new MagicLinkNotification($token, $email, $this->ttlMinutes()));
    }

    /**
     * Has this address asked for too many links?
     *
     * Limited by address as well as by IP: rate limiting only the IP lets
     * somebody on a big NAT lock out a whole office, and limiting only the
     * address lets one machine walk a list of them.
     */
    public function tooManyRequests(string $email, ?Request $request = null): bool
    {
        $perEmail = (int) config('base-tenant.passwordless.magic_links.max_per_hour', 5);

        return RateLimiter::tooManyAttempts($this->emailKey($email), $perEmail)
            || RateLimiter::tooManyAttempts($this->ipKey($request), $perEmail * 4);
    }

    public function recordRequest(string $email, ?Request $request = null): void
    {
        RateLimiter::hit($this->emailKey($email), 3600);
        RateLimiter::hit($this->ipKey($request), 3600);
    }

    public function secondsUntilRetry(string $email): int
    {
        return RateLimiter::availableIn($this->emailKey($email));
    }

    /**
     * Spend a token and return whose it was, or null.
     *
     * The update is conditional on the row still being unspent, so two
     * requests arriving with the same token in the same instant cannot both
     * win: the second one updates zero rows and gets nothing. Reading, then
     * writing, would let both through.
     */
    public function consume(string $token): ?User
    {
        $fingerprint = MagicLink::fingerprint($token);

        return DB::transaction(function () use ($fingerprint): ?User {
            $claimed = MagicLink::query()
                ->usable()
                ->where('token', $fingerprint)
                ->update(['consumed_at' => now()]);

            if ($claimed === 0) {
                return null;
            }

            $link = MagicLink::query()->where('token', $fingerprint)->first();

            $user = $link?->user;

            if ($user !== null) {
                ActivityLogService::log(
                    subject: $user,
                    action: 'auth.magic_link_used',
                );
            }

            return $user;
        });
    }

    /**
     * Drop spent and expired links. They are a record of who asked to sign in
     * and when, which is worth keeping for as long as it is useful and no
     * longer.
     */
    public function prune(int $days = 7): int
    {
        return MagicLink::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    protected function emailKey(string $email): string
    {
        return 'base-tenant:magic-link:email:'.hash('sha256', mb_strtolower(trim($email)));
    }

    protected function ipKey(?Request $request): string
    {
        return 'base-tenant:magic-link:ip:'.($request?->ip() ?? 'unknown');
    }
}
