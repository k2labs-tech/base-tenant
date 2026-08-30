<?php

declare(strict_types=1);

namespace Base\Tenant\Metering;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * One declared metric: what it counts, when it resets, and which plan feature
 * caps it.
 *
 * Metrics are declared in config rather than discovered from the rows, so a
 * metric with no usage yet still has a limit, a period and a place in the
 * usage screen.
 */
final class Metric
{
    /**
     * A counter accumulates over its period and resets; a gauge is a level
     * that goes up and down and never resets. Storage is a gauge, API calls
     * are a counter, and the difference decides both what `set()` means and
     * what gets reported to the billing provider.
     */
    public const COUNTER = 'counter';

    public const GAUGE = 'gauge';

    public const RESETS = ['none', 'day', 'month', 'year'];

    public function __construct(
        public readonly string $key,
        public readonly string $type = self::COUNTER,
        public readonly string $reset = 'month',
        public readonly ?string $feature = null,
        public readonly int $scale = 1,
        public readonly ?string $stripeMeter = null,
        public readonly ?string $label = null,
    ) {
        if (! in_array($this->type, [self::COUNTER, self::GAUGE], true)) {
            throw new InvalidArgumentException(
                "Metric `{$key}` has an unknown type `{$this->type}`; expected counter or gauge."
            );
        }

        if (! in_array($this->reset, self::RESETS, true)) {
            throw new InvalidArgumentException(
                "Metric `{$key}` has an unknown reset `{$this->reset}`; expected one of: ".implode(', ', self::RESETS).'.'
            );
        }

        if ($this->scale < 1) {
            throw new InvalidArgumentException("Metric `{$key}` has a scale below 1.");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        $type = $config['type'] ?? self::COUNTER;

        // A gauge that reset every month would empty itself while the files it
        // measures are still on disk, so the default depends on the type.
        $reset = $config['reset'] ?? ($type === self::GAUGE ? 'none' : 'month');

        return new self(
            key: $key,
            type: $type,
            reset: $reset,
            feature: $config['feature'] ?? null,
            scale: (int) ($config['scale'] ?? 1),
            stripeMeter: $config['stripe_meter'] ?? null,
            label: $config['label'] ?? null,
        );
    }

    /**
     * The period key a movement at this moment belongs to.
     *
     * Empty string, not null: see the migration for why.
     */
    public function period(?DateTimeInterface $at = null): string
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        return match ($this->reset) {
            'day' => $at->format('Y-m-d'),
            'month' => $at->format('Y-m'),
            'year' => $at->format('Y'),
            default => '',
        };
    }

    /**
     * The period keys of the last `$count` periods, oldest first, including
     * the current one. A metric that never resets has exactly one period.
     *
     * @return list<string>
     */
    public function periods(int $count): array
    {
        if ($this->reset === 'none') {
            return [''];
        }

        $step = match ($this->reset) {
            'day' => 'subDays',
            'month' => 'subMonths',
            default => 'subYears',
        };

        $periods = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            /** @var CarbonInterface $moment */
            $moment = Carbon::now()->{$step}($i);

            $periods[] = $this->period($moment);
        }

        return $periods;
    }

    public function isGauge(): bool
    {
        return $this->type === self::GAUGE;
    }

    /**
     * The plan allowance for this metric, in the metric's own unit.
     *
     * The feature is written in whatever unit reads well on a pricing page --
     * gigabytes, thousands of calls -- while the metric counts in the unit the
     * code actually has to hand. `scale` is the bridge, and -1 stays -1
     * because unlimited does not multiply.
     */
    public function limitFrom(int $featureValue): int
    {
        return $featureValue < 0 ? -1 : $featureValue * $this->scale;
    }
}
