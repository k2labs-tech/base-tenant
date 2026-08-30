<?php

declare(strict_types=1);

namespace Base\Tenant\Metering;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Support\Module;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point for usage metering.
 *
 *     Meter::increment('checks.run');                 // account in context
 *     Meter::for($account)->current('storage.bytes'); // a specific account
 */
class MeterManager
{
    public function __construct(
        protected MetricRegistry $metrics,
        protected UsageStore $store,
    ) {}

    public function for(Account|string|null $account): MeterSet
    {
        Module::ensure(Module::METERING);

        if (is_string($account)) {
            $model = config('base-tenant.models.account', Account::class);
            $account = $model::find($account);
        }

        return new MeterSet($account, $this->metrics, $this->store);
    }

    /**
     * The meters of the account in context.
     *
     * Named `meters()` rather than `current()` because `current()` is taken by
     * the reading of a single metric, which is the call this module is
     * actually used for.
     */
    public function meters(): MeterSet
    {
        return $this->for(Tenant::current());
    }

    public function metrics(): MetricRegistry
    {
        return $this->metrics;
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function increment(string $metric, int $by = 1, array $options = []): int
    {
        return $this->meters()->increment($metric, $by, $options);
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function decrement(string $metric, int $by = 1, array $options = []): int
    {
        return $this->meters()->decrement($metric, $by, $options);
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function set(string $metric, int $value, array $options = []): int
    {
        return $this->meters()->set($metric, $value, $options);
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function incrementOrFail(string $metric, int $by = 1, array $options = []): int
    {
        return $this->meters()->incrementOrFail($metric, $by, $options);
    }

    public function current(string $metric): int
    {
        return $this->meters()->current($metric);
    }

    public function limit(string $metric): int
    {
        return $this->meters()->limit($metric);
    }

    public function remaining(string $metric): int
    {
        return $this->meters()->remaining($metric);
    }

    public function percentage(string $metric): ?int
    {
        return $this->meters()->percentage($metric);
    }

    public function wouldExceed(string $metric, int $by = 1): bool
    {
        return $this->meters()->wouldExceed($metric, $by);
    }

    /** @return array<string, int> */
    public function history(string $metric, int $periods = 12): array
    {
        return $this->meters()->history($metric, $periods);
    }

    /** @return array<int, array{metric: Metric, value: int, limit: int, remaining: int, percentage: int|null}> */
    public function summary(): array
    {
        return $this->meters()->summary();
    }
}
