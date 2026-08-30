<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Metering\MeterManager;
use Base\Tenant\Metering\MeterSet;
use Base\Tenant\Metering\Metric;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\Account;
use Illuminate\Support\Facades\Facade;

/**
 * @method static MeterSet for(Account|string|null $account)
 * @method static MeterSet meters()
 * @method static MetricRegistry metrics()
 * @method static int increment(string $metric, int $by = 1, array $options = [])
 * @method static int decrement(string $metric, int $by = 1, array $options = [])
 * @method static int set(string $metric, int $value, array $options = [])
 * @method static int incrementOrFail(string $metric, int $by = 1, array $options = [])
 * @method static int current(string $metric)
 * @method static int limit(string $metric)
 * @method static int remaining(string $metric)
 * @method static int|null percentage(string $metric)
 * @method static bool wouldExceed(string $metric, int $by = 1)
 * @method static array<string, int> history(string $metric, int $periods = 12)
 * @method static array<int, array{metric: Metric, value: int, limit: int, remaining: int, percentage: int|null}> summary()
 *
 * @see MeterManager
 */
class Meter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MeterManager::class;
    }
}
