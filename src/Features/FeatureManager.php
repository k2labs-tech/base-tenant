<?php

declare(strict_types=1);

namespace Base\Tenant\Features;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;

/**
 * Entry point for feature checks.
 *
 *     Feature::active('export');                  // account in context
 *     Feature::for($account)->active('export');   // a specific account
 */
class FeatureManager
{
    public function for(Account|string|null $account): FeatureSet
    {
        if (is_string($account)) {
            $model = config('base-tenant.models.account', Account::class);
            $account = $model::find($account);
        }

        return new FeatureSet($account);
    }

    public function current(): FeatureSet
    {
        return new FeatureSet(Tenant::current());
    }

    public function active(string $feature): bool
    {
        return $this->current()->active($feature);
    }

    public function inactive(string $feature): bool
    {
        return $this->current()->inactive($feature);
    }

    public function limit(string $feature): int
    {
        return $this->current()->limit($feature);
    }

    /**
     * Usage may be omitted when a declared metric caps this feature; the
     * meter is then read for it. See FeatureSet::withinLimit().
     */
    public function withinLimit(string $feature, ?int $currentUsage = null): bool
    {
        return $this->current()->withinLimit($feature, $currentUsage);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->current()->all();
    }
}
