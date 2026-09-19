<?php

declare(strict_types=1);

namespace Base\Tenant\Security;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;
use Base\Tenant\Services\ActivityLogService;
use Base\Tenant\Support\IpRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads and applies the security rules of an account.
 *
 * Everything that enforces a rule -- three middleware, the invitation service,
 * the screen -- asks this class rather than reading settings itself, so a rule
 * has one interpretation and not four slightly different ones.
 */
class SecurityPolicyManager
{
    /**
     * Policies already read this request, by account. Three middleware and a
     * screen ask for the same policy on every request; without this each ask
     * is a query against `settings`, and Livewire updates pay it several
     * times over.
     *
     * The manager is a singleton, so under Octane or a queue worker this
     * would outlive the request: the provider clears it when a request or a
     * job starts (`registerSecurityPolicyLifecycle()`).
     *
     * @var array<string, SecurityPolicySettings>
     */
    protected array $resolved = [];

    public function for(?Account $account = null): SecurityPolicySettings
    {
        $account ??= Tenant::current();

        if ($account === null) {
            // No account in context means no customer rules to apply. The
            // permissive default is deliberate: the alternative would refuse
            // requests during sign-up and in console work.
            return new SecurityPolicySettings;
        }

        return $this->resolved[(string) $account->getKey()]
            ??= SecurityPolicySettings::for($account);
    }

    /**
     * Drop what was read, for the next caller to read afresh. `update()` does
     * this itself; a host writing the `security` settings group directly has
     * to.
     */
    public function forget(?Account $account = null): void
    {
        if ($account === null) {
            $this->resolved = [];

            return;
        }

        unset($this->resolved[(string) $account->getKey()]);
    }

    public function enabled(): bool
    {
        return (bool) config('base-tenant.security.enabled', true);
    }

    // -----------------------------------------------------------------
    // Two factor
    // -----------------------------------------------------------------

    public function requiresTwoFactor(?Account $account = null): bool
    {
        return $this->enabled() && $this->for($account)->requireTwoFactor;
    }

    /**
     * When this user stops being allowed in without a second factor.
     *
     * Counted from `two_factor_required_from`, stamped when the rule was
     * switched on. Neither obvious alternative works: `created_at` hands
     * somebody who has used the product for a year a deadline already in the
     * past, and counting from "now" means the deadline never arrives.
     */
    public function twoFactorDeadlineFor(User $user, ?Account $account = null): CarbonInterface
    {
        $policy = $this->for($account);

        $from = $user->two_factor_required_from ?? $user->created_at ?? now();

        return Carbon::parse($from)->addHours(max(0, $policy->twoFactorGraceHours));
    }

    /**
     * Start the clock for everybody already in the account.
     *
     * Called when the rule is switched on. Only stamps users who have no
     * timestamp yet, so switching the rule off and on again does not hand
     * somebody a fresh grace period they already used.
     */
    public function startTwoFactorClock(Account $account): void
    {
        User::query()
            ->whereNull('two_factor_required_from')
            ->where(fn ($query) => $query
                ->where('account_id', $account->getKey())
                ->orWhereHas('accounts', fn ($accounts) => $accounts->whereKey($account->getKey())))
            ->update(['two_factor_required_from' => now()]);
    }

    /**
     * Start the clock for one user joining an account that already has the
     * rule on.
     *
     * Without this, somebody invited on Friday into an account that switched
     * the rule on Monday would fall back to `created_at` and be overdue on
     * their first request, with no grace period at all. Never re-stamps: a
     * user who already has a deadline keeps it.
     */
    public function startTwoFactorClockFor(User $user, Account $account): void
    {
        if (! $this->requiresTwoFactor($account)) {
            return;
        }

        if ($user->two_factor_required_from !== null) {
            return;
        }

        $user->forceFill(['two_factor_required_from' => now()])->save();
    }

    public function twoFactorIsOverdue(User $user, ?Account $account = null): bool
    {
        if (! $this->requiresTwoFactor($account) || $user->hasTwoFactorEnabled()) {
            return false;
        }

        return $this->twoFactorDeadlineFor($user, $account)->isPast();
    }

    // -----------------------------------------------------------------
    // Email domains
    // -----------------------------------------------------------------

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    public function normalizeDomains(array $domains): array
    {
        $normalized = [];

        foreach ($domains as $domain) {
            $domain = mb_strtolower(trim((string) $domain));
            $domain = ltrim($domain, '@');

            // People paste an address when asked for a domain.
            if (str_contains($domain, '@')) {
                $domain = substr($domain, strrpos($domain, '@') + 1);
            }

            if ($domain !== '' && str_contains($domain, '.')) {
                $normalized[] = $domain;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function allowsEmail(string $email, ?Account $account = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $allowed = $this->for($account)->allowedEmailDomains;

        if ($allowed === []) {
            return true;
        }

        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        return in_array(mb_strtolower(substr($email, $at + 1)), $allowed, true);
    }

    // -----------------------------------------------------------------
    // IP allowlist
    // -----------------------------------------------------------------

    public function allowsIp(string $ip, ?Account $account = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $policy = $this->for($account);

        // An empty list never blocks. A list that is switched to `enforce`
        // while empty would otherwise lock out every member of the account,
        // including whoever is editing the rule.
        if ($policy->ipAllowlist === []) {
            return true;
        }

        return IpRange::matchesAny($ip, $policy->ipAllowlist);
    }

    public function enforcesIp(?Account $account = null): bool
    {
        return $this->enabled() && $this->for($account)->enforcesIp();
    }

    public function warnsOnIp(?Account $account = null): bool
    {
        return $this->enabled() && $this->for($account)->warnsOnIp();
    }

    // -----------------------------------------------------------------
    // Sessions
    // -----------------------------------------------------------------

    public function sessionTimeoutMinutes(?Account $account = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        return max(0, $this->for($account)->sessionTimeoutMinutes);
    }

    // -----------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------

    /**
     * Save a policy and record what changed.
     *
     * Auditing this is not decoration: "who turned off the IP allowlist and
     * when" is the first question after an incident, and the settings store
     * keeps no history of its own.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(Account $account, array $values): SecurityPolicySettings
    {
        $policy = $this->for($account);
        $before = $policy->toArray();

        try {
            $policy->fill($values)->save();
        } catch (Throwable $exception) {
            // The memoised instance now carries values that never reached the
            // store. Drop it rather than serve them for the rest of the request.
            $this->forget($account);

            throw $exception;
        }

        $this->resolved[(string) $account->getKey()] = $policy;

        if ($policy->requireTwoFactor && ! ($before['requireTwoFactor'] ?? false)) {
            $this->startTwoFactorClock($account);
        }

        ActivityLogService::log(
            subject: $account,
            action: 'security.policy_changed',
            oldValues: $before,
            newValues: $policy->toArray(),
        );

        return $policy;
    }
}
