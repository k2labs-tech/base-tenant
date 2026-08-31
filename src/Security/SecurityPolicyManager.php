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

/**
 * Reads and applies the security rules of an account.
 *
 * Everything that enforces a rule -- three middleware, the invitation service,
 * the screen -- asks this class rather than reading settings itself, so a rule
 * has one interpretation and not four slightly different ones.
 */
class SecurityPolicyManager
{
    public function for(?Account $account = null): SecurityPolicySettings
    {
        $account ??= Tenant::current();

        if ($account === null) {
            // No account in context means no customer rules to apply. The
            // permissive default is deliberate: the alternative would refuse
            // requests during sign-up and in console work.
            return new SecurityPolicySettings;
        }

        return SecurityPolicySettings::for($account);
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

        $policy->fill($values)->save();

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
