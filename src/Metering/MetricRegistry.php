<?php

declare(strict_types=1);

namespace Base\Tenant\Metering;

use InvalidArgumentException;

/**
 * The declared metrics, read from config once per request.
 *
 * Metering refuses unknown keys on purpose. A typo in `Meter::increment()`
 * would otherwise open a brand new counter that nothing caps, nothing shows
 * and nobody notices -- the account would sail past its limit with the meter
 * reading zero.
 */
class MetricRegistry
{
    /** @var array<string, Metric>|null */
    protected ?array $metrics = null;

    /** @return array<string, Metric> */
    public function all(): array
    {
        if ($this->metrics !== null) {
            return $this->metrics;
        }

        $declared = config('base-tenant.metering.metrics', []);

        $metrics = [];

        foreach ($declared as $key => $config) {
            $metrics[$key] = Metric::fromConfig($key, is_array($config) ? $config : []);
        }

        return $this->metrics = $metrics;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): Metric
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException(
            "Metric `{$key}` is not declared. Add it to config('base-tenant.metering.metrics')."
        );
    }

    /**
     * The metric a plan feature caps, if any.
     *
     * This is what lets `Feature::withinLimit('max_storage_gb')` answer without
     * being handed a usage figure: the feature knows its metric, and the metric
     * knows how to read itself.
     */
    public function forFeature(string $feature): ?Metric
    {
        foreach ($this->all() as $metric) {
            if ($metric->feature === $feature) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * Drop the memoised list. Only useful when config changes mid-process,
     * which in practice means tests.
     */
    public function flush(): void
    {
        $this->metrics = null;
    }
}
