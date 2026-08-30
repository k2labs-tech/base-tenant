<?php

declare(strict_types=1);

namespace Base\Tenant\Features;

use Base\Tenant\Facades\Meter;
use Base\Tenant\Metering\Metric;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Feature;
use Base\Tenant\Services\FeatureService;
use Base\Tenant\Support\Module;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * The effective features of one account: the subscription plan, with any
 * per-account override on top.
 */
class FeatureSet
{
    public function __construct(protected ?Account $account) {}

    public function active(string $feature): bool
    {
        if (! $this->account) {
            return false;
        }

        $override = $this->override($feature);

        if ($override !== null) {
            return is_bool($override) ? $override : (int) $override !== 0;
        }

        return FeatureService::planAllows($this->account, $feature);
    }

    public function inactive(string $feature): bool
    {
        return ! $this->active($feature);
    }

    /**
     * Numeric allowance for a feature. -1 means unlimited.
     */
    public function limit(string $feature): int
    {
        if (! $this->account) {
            return 0;
        }

        $override = $this->override($feature);

        if ($override !== null) {
            return (int) $override;
        }

        return FeatureService::planLimit($this->account, $feature);
    }

    /**
     * Is there room left under this feature's allowance?
     *
     * When the feature caps a declared metric, the usage is read from the
     * meter and the comparison happens in the metric's own unit -- the feature
     * says gigabytes because that is what reads well on a pricing page, and
     * the meter counts bytes because that is what the code has to hand.
     * Comparing the two without scaling would let an account store a gigabyte
     * for every byte it is owed.
     *
     * Pass `$currentUsage` for anything not metered, such as a count of rows.
     *
     * @throws InvalidArgumentException when nothing is metered and nothing was passed
     */
    public function withinLimit(string $feature, ?int $currentUsage = null): bool
    {
        $limit = $this->limit($feature);

        if ($limit === -1) {
            return true;
        }

        $metric = $this->meteredBy($feature);

        if ($currentUsage === null && $metric === null) {
            throw new InvalidArgumentException(
                "Feature `{$feature}` has no metric declared, so its usage cannot be read. ".
                'Pass the current usage, or declare a metric with this feature key in '.
                "config('base-tenant.metering.metrics')."
            );
        }

        if ($currentUsage !== null) {
            return $currentUsage < $limit;
        }

        return Meter::for($this->account)->current($metric->key) < $metric->limitFrom($limit);
    }

    /**
     * The metric this feature caps, if metering is on and one is declared.
     */
    protected function meteredBy(string $feature): ?Metric
    {
        if (! Module::enabled(Module::METERING)) {
            return null;
        }

        return app(MetricRegistry::class)->forFeature($feature);
    }

    public function value(string $feature): mixed
    {
        return $this->override($feature) ?? FeatureService::planValue($this->account, $feature);
    }

    /**
     * Turn a feature on, off or to a numeric allowance for this account only.
     */
    public function set(string $feature, mixed $value, ?DateTimeInterface $expiresAt = null): Feature
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };

        $feature = Feature::updateOrCreate(
            ['account_id' => $this->account->getKey(), 'key' => $feature],
            [
                'value' => Feature::serializeValue($value, $type),
                'type' => $type,
                'expires_at' => $expiresAt,
            ]
        );

        $this->flush();

        return $feature;
    }

    public function forget(string $feature): void
    {
        Feature::where('account_id', $this->account?->getKey())
            ->where('key', $feature)
            ->delete();

        $this->flush();
    }

    /**
     * Every feature this account resolves to, overrides merged over the plan.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! $this->account) {
            return [];
        }

        return [...FeatureService::planFeatures($this->account), ...$this->overrides()];
    }

    protected function override(string $feature): mixed
    {
        return $this->overrides()[$feature] ?? null;
    }

    /** @return array<string, mixed> */
    protected function overrides(): array
    {
        if (! $this->account) {
            return [];
        }

        return FeatureService::overridesFor($this->account);
    }

    protected function flush(): void
    {
        FeatureService::flush($this->account);
    }
}
