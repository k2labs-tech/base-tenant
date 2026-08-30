<?php

declare(strict_types=1);

namespace Base\Tenant\Metering;

use Base\Tenant\Models\UsageCounter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads and moves the counters. The only place that writes to
 * `usage_counters`.
 *
 * Every movement is a single statement that adds to whatever is stored, never
 * a read followed by a write: two requests incrementing at the same moment
 * would both read the same total and one of the two movements would vanish.
 * On a number the customer is billed on, that is not an acceptable rounding.
 */
class UsageStore
{
    protected function connection(): ConnectionInterface
    {
        return DB::connection((new UsageCounter)->getConnectionName());
    }

    protected function table(): string
    {
        return (new UsageCounter)->getTable();
    }

    public function current(string $accountId, string $metric, string $period): int
    {
        $value = $this->connection()->table($this->table())
            ->where('account_id', $accountId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('value');

        return (int) ($value ?? 0);
    }

    /**
     * Every period of one metric that has a row, keyed by period.
     *
     * @param  list<string>  $periods
     * @return array<string, int>
     */
    public function currentMany(string $accountId, string $metric, array $periods): array
    {
        return $this->connection()->table($this->table())
            ->where('account_id', $accountId)
            ->where('metric', $metric)
            ->whereIn('period', $periods)
            ->pluck('value', 'period')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /**
     * Add `$delta` to a counter and return the resulting total.
     *
     * The stored total is exact under concurrency. The number returned may
     * belong to a movement another request made a moment later, which matters
     * to nobody: it is used to decide whether a usage warning is due, and both
     * requests would decide the same thing.
     */
    public function move(string $accountId, string $metric, string $period, int $delta): int
    {
        $connection = $this->connection();
        $table = $this->table();
        $now = now();

        $insert = [
            Str::uuid()->toString(),
            $accountId,
            $metric,
            $period,
            $delta,
            $now,
            $now,
        ];

        $sql = match ($driver = $connection->getDriverName()) {
            'mysql', 'mariadb' => "insert into {$table} (id, account_id, metric, period, value, created_at, updated_at)
                values (?, ?, ?, ?, ?, ?, ?)
                on duplicate key update value = {$table}.value + ?, updated_at = ?",

            'pgsql', 'sqlite' => "insert into {$table} (id, account_id, metric, period, value, created_at, updated_at)
                values (?, ?, ?, ?, ?, ?, ?)
                on conflict (account_id, metric, period)
                do update set value = {$table}.value + excluded.value, updated_at = excluded.updated_at",

            default => throw new RuntimeException(
                "Usage metering has no atomic increment for the `{$driver}` driver."
            ),
        };

        $bindings = in_array($driver, ['mysql', 'mariadb'], true)
            ? [...$insert, $delta, $now]
            : $insert;

        $connection->statement($sql, $bindings);

        return $this->current($accountId, $metric, $period);
    }

    /**
     * Add `$delta` only if the result stays within `$limit`, and say whether
     * it did.
     *
     * This one does take a lock. The fast path above is happy to be a moment
     * out of date; a limit that is enforced from a stale reading is not a
     * limit, so the row is held for the length of the check.
     *
     * @return array{0: bool, 1: int} allowed, and the total (before the move
     *                                when it was refused)
     */
    public function moveWithin(string $accountId, string $metric, string $period, int $delta, int $limit): array
    {
        return $this->connection()->transaction(function () use ($accountId, $metric, $period, $delta, $limit): array {
            // `lockForUpdate` locks rows that exist. Opening the counter first
            // means there is always one to lock, and the insert races safely
            // with itself because the unique index settles the tie.
            $this->open($accountId, $metric, $period);

            $current = (int) $this->connection()->table($this->table())
                ->where('account_id', $accountId)
                ->where('metric', $metric)
                ->where('period', $period)
                ->lockForUpdate()
                ->value('value');

            if ($limit !== -1 && $current + $delta > $limit) {
                return [false, $current];
            }

            $this->connection()->table($this->table())
                ->where('account_id', $accountId)
                ->where('metric', $metric)
                ->where('period', $period)
                ->update([
                    'value' => $current + $delta,
                    'updated_at' => now(),
                ]);

            return [true, $current + $delta];
        });
    }

    /**
     * Put a counter at an exact value, whatever it held before.
     */
    public function put(string $accountId, string $metric, string $period, int $value): int
    {
        $this->open($accountId, $metric, $period);

        $this->connection()->table($this->table())
            ->where('account_id', $accountId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->update(['value' => $value, 'updated_at' => now()]);

        return $value;
    }

    /**
     * Remember that the account has been warned at this level, so the next
     * increment does not warn again. Null clears it.
     */
    public function markNotified(string $accountId, string $metric, string $period, ?int $threshold): void
    {
        $this->connection()->table($this->table())
            ->where('account_id', $accountId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->update(['notified_threshold' => $threshold]);
    }

    public function notifiedThreshold(string $accountId, string $metric, string $period): ?int
    {
        $value = $this->connection()->table($this->table())
            ->where('account_id', $accountId)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('notified_threshold');

        return $value === null ? null : (int) $value;
    }

    /**
     * Make sure the counter row exists, without touching its value.
     */
    protected function open(string $accountId, string $metric, string $period): void
    {
        $this->move($accountId, $metric, $period, 0);
    }
}
