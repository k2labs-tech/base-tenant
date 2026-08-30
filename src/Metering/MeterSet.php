<?php

declare(strict_types=1);

namespace Base\Tenant\Metering;

use Base\Tenant\Exceptions\UsageLimitExceededException;
use Base\Tenant\Facades\Feature;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\UsageEvent;
use Base\Tenant\Notifications\UsageThresholdReached;
use Illuminate\Database\Eloquent\Model;

/**
 * The meters of one account.
 *
 *     Meter::increment('checks.run');
 *     Meter::for($account)->remaining('storage.bytes');
 */
class MeterSet
{
    /**
     * The levels an account is warned at, highest first. Crossing one sends a
     * notification; crossing back below it arms the warning again.
     */
    public const THRESHOLDS = [100, 80];

    public function __construct(
        protected ?Account $account,
        protected MetricRegistry $metrics,
        protected UsageStore $store,
    ) {}

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function increment(string $metric, int $by = 1, array $options = []): int
    {
        return $this->record($metric, $by, $options);
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function decrement(string $metric, int $by = 1, array $options = []): int
    {
        return $this->record($metric, -$by, $options);
    }

    /**
     * Put a meter at an exact figure.
     *
     * The event trail records the movement that got it there, so the events
     * keep summing to the counter even when something outside the meter --
     * a reconciliation, an import -- decided what the total should be.
     *
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    public function set(string $metric, int $value, array $options = []): int
    {
        $definition = $this->metrics->get($metric);

        if (! $this->account) {
            return 0;
        }

        $period = $definition->period();
        $before = $this->store->current($this->account->getKey(), $metric, $period);

        $this->store->put($this->account->getKey(), $metric, $period, $value);
        $this->writeEvent($definition, $period, $value - $before, $options);
        $this->reviewThreshold($definition, $period, $value);

        return $value;
    }

    public function current(string $metric): int
    {
        $definition = $this->metrics->get($metric);

        if (! $this->account) {
            return 0;
        }

        return $this->store->current($this->account->getKey(), $metric, $definition->period());
    }

    /**
     * The allowance for this metric in the metric's own unit. -1 is unlimited,
     * which is also what an undeclared cap means: a metric nobody capped is a
     * measurement, not a limit.
     */
    public function limit(string $metric): int
    {
        $definition = $this->metrics->get($metric);

        if (! $definition->feature || ! $this->account) {
            return -1;
        }

        return $definition->limitFrom(
            Feature::for($this->account)->limit($definition->feature)
        );
    }

    /**
     * How much is left. -1 when unlimited, never below zero otherwise.
     */
    public function remaining(string $metric): int
    {
        $limit = $this->limit($metric);

        if ($limit === -1) {
            return -1;
        }

        return max(0, $limit - $this->current($metric));
    }

    /**
     * Consumed share of the allowance, or null when there is no allowance to
     * take a share of.
     */
    public function percentage(string $metric): ?int
    {
        $limit = $this->limit($metric);

        if ($limit === -1 || $limit === 0) {
            return null;
        }

        return (int) floor($this->current($metric) / $limit * 100);
    }

    public function wouldExceed(string $metric, int $by = 1): bool
    {
        $limit = $this->limit($metric);

        return $limit !== -1 && $this->current($metric) + $by > $limit;
    }

    /**
     * Consume the allowance, or refuse.
     *
     * Unlike `increment()`, this one is enforced against a locked reading, so
     * two requests arriving together cannot both be told there was room for
     * the last unit.
     *
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     *
     * @throws UsageLimitExceededException
     */
    public function incrementOrFail(string $metric, int $by = 1, array $options = []): int
    {
        $definition = $this->metrics->get($metric);

        if (! $this->account) {
            return 0;
        }

        $period = $definition->period();

        [$allowed, $value] = $this->store->moveWithin(
            $this->account->getKey(),
            $metric,
            $period,
            $by,
            $this->limit($metric),
        );

        if (! $allowed) {
            throw UsageLimitExceededException::for(
                $definition,
                $value,
                $this->limit($metric),
                $this->account,
            );
        }

        $this->writeEvent($definition, $period, $by, $options);
        $this->reviewThreshold($definition, $period, $value);

        return $value;
    }

    /**
     * The last `$periods` periods of a metric, oldest first, keyed by period.
     *
     * Periods with no movement come back as zero rather than missing: a chart
     * with a gap where a quiet month should be reads as lost data.
     *
     * @return array<string, int>
     */
    public function history(string $metric, int $periods = 12): array
    {
        $definition = $this->metrics->get($metric);

        $keys = $definition->periods($periods);

        if (! $this->account) {
            return array_fill_keys($keys, 0);
        }

        $stored = $this->store->currentMany($this->account->getKey(), $metric, $keys);

        $history = [];

        foreach ($keys as $key) {
            $history[$key] = $stored[$key] ?? 0;
        }

        return $history;
    }

    /**
     * Every declared metric with its current standing, for the usage screen.
     *
     * @return array<int, array{metric: Metric, value: int, limit: int, remaining: int, percentage: int|null}>
     */
    public function summary(): array
    {
        return array_map(fn (Metric $metric): array => [
            'metric' => $metric,
            'value' => $this->current($metric->key),
            'limit' => $this->limit($metric->key),
            'remaining' => $this->remaining($metric->key),
            'percentage' => $this->percentage($metric->key),
        ], array_values($this->metrics->all()));
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    protected function record(string $metric, int $delta, array $options): int
    {
        $definition = $this->metrics->get($metric);

        if (! $this->account || $delta === 0) {
            return $this->account ? $this->current($metric) : 0;
        }

        $period = $definition->period();

        $value = $this->store->move($this->account->getKey(), $metric, $period, $delta);

        $this->writeEvent($definition, $period, $delta, $options);
        $this->reviewThreshold($definition, $period, $value);

        return $value;
    }

    /**
     * @param  array{subject?: Model|null, metadata?: array<string, mixed>}  $options
     */
    protected function writeEvent(Metric $metric, string $period, int $delta, array $options): void
    {
        if ($delta === 0) {
            return;
        }

        $subject = $options['subject'] ?? null;

        UsageEvent::create([
            'account_id' => $this->account?->getKey(),
            'metric' => $metric->key,
            'period' => $period,
            'delta' => $delta,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $options['metadata'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Warn the account once per level crossed, and re-arm when it drops back.
     */
    protected function reviewThreshold(Metric $metric, string $period, int $value): void
    {
        if (! $this->account || ! $metric->feature) {
            return;
        }

        $limit = $this->limit($metric->key);

        if ($limit <= 0) {
            return;
        }

        $percentage = (int) floor($value / $limit * 100);

        $crossed = null;

        foreach (self::THRESHOLDS as $threshold) {
            if ($percentage >= $threshold) {
                $crossed = $threshold;
                break;
            }
        }

        $notified = $this->store->notifiedThreshold($this->account->getKey(), $metric->key, $period);

        if ($crossed === $notified) {
            return;
        }

        $this->store->markNotified($this->account->getKey(), $metric->key, $period, $crossed);

        // Dropping back below a level only re-arms the warning; nobody needs
        // an email to say their usage went down.
        if ($crossed === null || ($notified !== null && $crossed < $notified)) {
            return;
        }

        $this->notify(new UsageThresholdReached($metric, $crossed, $value, $limit));
    }

    /**
     * The account owner gets the warning.
     *
     * Widening this to everyone holding `accounts.billing` would be the
     * obvious next step, but permission checks are scoped to the tenant in
     * context and the account being metered is not always the one in context
     * -- a queued job reconciling every account would ask the wrong question
     * of every user. The owner is the one recipient that is right regardless
     * of who is looking.
     */
    protected function notify(UsageThresholdReached $notification): void
    {
        $this->account?->owner?->notify($notification);
    }
}
