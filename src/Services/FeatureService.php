<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Features\FeatureSet;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Feature;

/**
 * Resolves what an account is entitled to.
 *
 * Three layers, innermost wins: the subscription plan defined in config, then
 * per-account overrides stored in the `features` table.
 */
class FeatureService
{
    /** @var array<string, array<string, mixed>> */
    protected static array $overrideCache = [];

    public static function getPlanForAccount(Account $account): string
    {
        if (! config('base-tenant.subscription.enabled', true)) {
            return config('base-tenant.subscription.default_plan', 'free');
        }

        $subscription = $account->subscriptions()->active()->first();

        if (! $subscription) {
            return 'free';
        }

        $priceId = $subscription->items->first()?->stripe_price;

        foreach (config('base-tenant.plans', []) as $key => $plan) {
            if (isset($plan['stripe_price_id']) && $plan['stripe_price_id'] === $priceId) {
                return $key;
            }
        }

        return 'free';
    }

    public static function getPlanConfig(string $planKey): ?array
    {
        return config("base-tenant.plans.{$planKey}");
    }

    /**
     * The raw plan value for a feature, ignoring per-account overrides.
     */
    public static function planValue(?Account $account, string $feature): mixed
    {
        if (! $account) {
            return null;
        }

        $planKey = static::getPlanForAccount($account);

        return config("base-tenant.plans.{$planKey}.features.{$feature}");
    }

    public static function planAllows(Account $account, string $feature): bool
    {
        $value = static::planValue($account, $feature);

        if ($value === null) {
            return false;
        }

        return is_bool($value) ? $value : (int) $value !== 0;
    }

    public static function planLimit(Account $account, string $feature): int
    {
        return (int) (static::planValue($account, $feature) ?? 0);
    }

    /** @return array<string, mixed> */
    public static function planFeatures(Account $account): array
    {
        $planKey = static::getPlanForAccount($account);

        return config("base-tenant.plans.{$planKey}.features", []);
    }

    /**
     * Per-account overrides, resolved once per request.
     *
     * @return array<string, mixed>
     */
    public static function overridesFor(Account $account): array
    {
        $key = (string) $account->getKey();

        if (array_key_exists($key, static::$overrideCache)) {
            return static::$overrideCache[$key];
        }

        return static::$overrideCache[$key] = Feature::query()
            ->where('account_id', $key)
            ->active()
            ->get()
            ->mapWithKeys(fn (Feature $feature): array => [$feature->key => $feature->casted_value])
            ->all();
    }

    public static function flush(?Account $account = null): void
    {
        if ($account === null) {
            static::$overrideCache = [];

            return;
        }

        unset(static::$overrideCache[(string) $account->getKey()]);
    }

    public static function accountCan(Account $account, string $feature): bool
    {
        return (new FeatureSet($account))->active($feature);
    }

    public static function getLimit(Account $account, string $feature): int
    {
        return (new FeatureSet($account))->limit($feature);
    }

    public static function isWithinLimit(Account $account, string $feature, int $currentUsage): bool
    {
        return (new FeatureSet($account))->withinLimit($feature, $currentUsage);
    }

    /** @return array<string, mixed> */
    public static function getAccountFeatures(Account $account): array
    {
        return (new FeatureSet($account))->all();
    }

    public static function getAccountPlanName(Account $account): string
    {
        $planKey = static::getPlanForAccount($account);

        return config("base-tenant.plans.{$planKey}.name", 'Free');
    }
}
