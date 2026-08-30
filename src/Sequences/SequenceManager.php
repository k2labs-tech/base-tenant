<?php

declare(strict_types=1);

namespace Base\Tenant\Sequences;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Sequence;
use Base\Tenant\Support\Module;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Correlative numbering that does not repeat and does not skip.
 *
 *     Sequence::next('bookings');                              // "00042"
 *     Sequence::next('invoices', format: 'F{year}-{number:5}'); // "F2026-00042"
 *     Sequence::peek('bookings');                              // without consuming
 *
 * The number is handed out inside a transaction holding a lock on the row.
 * `max(id) + 1` and an exposed autoincrement both look like they work until
 * two requests arrive together -- and a duplicated invoice number is the kind
 * of defect that is found by an auditor rather than by a test.
 */
class SequenceManager
{
    /**
     * How the number is rendered when no format is given: five digits, zero
     * padded. Enough for anything that is numbered by hand, and it sorts as
     * text in the same order as it sorts as a number.
     */
    public const DEFAULT_FORMAT = '{number:5}';

    /**
     * Take the next number.
     *
     * `$period` decides when the counter restarts: `year`, `month`, `day` or
     * `none`. A yearly sequence goes back to 1 on the first of January without
     * anyone running anything.
     */
    public function next(
        string $key,
        ?string $format = null,
        string $period = 'none',
        Account|string|null $account = null,
    ): string {
        Module::ensure(Module::SEQUENCES);

        $scope = $this->scope($account);
        $periodKey = $this->periodKey($period);

        return DB::transaction(function () use ($key, $format, $scope, $periodKey): string {
            $row = $this->lock($key, $scope, $periodKey, $format);

            $number = $row->next_value;

            Sequence::query()->whereKey($row->getKey())->update([
                'next_value' => $number + 1,
                'updated_at' => now(),
            ]);

            return $this->render($format ?? $row->format ?? self::DEFAULT_FORMAT, $number, $periodKey);
        });
    }

    /**
     * What `next()` would return, without taking it.
     *
     * Useful for showing a draft. Do not store what this returns: by the time
     * the record is saved somebody else may have taken it.
     */
    public function peek(
        string $key,
        ?string $format = null,
        string $period = 'none',
        Account|string|null $account = null,
    ): string {
        Module::ensure(Module::SEQUENCES);

        $scope = $this->scope($account);
        $periodKey = $this->periodKey($period);

        $row = Sequence::query()
            ->where('account_id', $scope)
            ->where('key', $key)
            ->where('period', $periodKey)
            ->first();

        return $this->render(
            $format ?? $row?->format ?? self::DEFAULT_FORMAT,
            $row->next_value ?? 1,
            $periodKey,
        );
    }

    /**
     * The raw counter, for a screen that wants the number rather than the
     * rendered reference.
     */
    public function current(string $key, string $period = 'none', Account|string|null $account = null): int
    {
        Module::ensure(Module::SEQUENCES);

        return (int) (Sequence::query()
            ->where('account_id', $this->scope($account))
            ->where('key', $key)
            ->where('period', $this->periodKey($period))
            ->value('next_value') ?? 1);
    }

    /**
     * Move a counter by hand.
     *
     * For migrating an existing product whose numbering has to continue where
     * the old system stopped. Not something an application should call in the
     * course of its work.
     */
    public function setNext(string $key, int $value, string $period = 'none', Account|string|null $account = null): void
    {
        Module::ensure(Module::SEQUENCES);

        if ($value < 1) {
            throw new InvalidArgumentException('A sequence cannot be set below 1.');
        }

        $scope = $this->scope($account);
        $periodKey = $this->periodKey($period);

        DB::transaction(function () use ($key, $value, $scope, $periodKey): void {
            $row = $this->lock($key, $scope, $periodKey, null);

            Sequence::query()->whereKey($row->getKey())->update(['next_value' => $value]);
        });
    }

    /**
     * Open the row if it is not there, then lock it.
     *
     * The insert races with itself safely: the unique index settles the tie
     * and the loser reads the row the winner created.
     */
    protected function lock(string $key, string $scope, string $period, ?string $format): Sequence
    {
        $row = Sequence::query()
            ->where('account_id', $scope)
            ->where('key', $key)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($row) {
            return $row;
        }

        Sequence::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'account_id' => $scope,
            'key' => $key,
            'period' => $period,
            'next_value' => 1,
            'format' => $format,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Sequence::query()
            ->where('account_id', $scope)
            ->where('key', $key)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Render a number.
     *
     * Tokens: `{number}`, `{number:N}` zero padded to N digits, `{year}`,
     * `{month}`, `{day}`. The date parts come from the period the number
     * belongs to where there is one, and from today otherwise -- a number
     * taken from the 2026 counter must not print 2027 because it was taken a
     * minute after midnight.
     */
    public function render(string $format, int $number, string $period = ''): string
    {
        $moment = $this->momentFor($period);

        $replacements = [
            '{year}' => $moment->format('Y'),
            '{month}' => $moment->format('m'),
            '{day}' => $moment->format('d'),
            '{number}' => (string) $number,
        ];

        $rendered = strtr($format, $replacements);

        return preg_replace_callback(
            '/\{number:(\d+)\}/',
            fn (array $matches): string => str_pad((string) $number, (int) $matches[1], '0', STR_PAD_LEFT),
            $rendered,
        ) ?? $rendered;
    }

    protected function momentFor(string $period): Carbon
    {
        if ($period === '') {
            return Carbon::now();
        }

        return match (substr_count($period, '-')) {
            0 => Carbon::createFromFormat('Y', $period)->startOfYear(),
            1 => Carbon::createFromFormat('Y-m', $period)->startOfMonth(),
            default => Carbon::createFromFormat('Y-m-d', $period)->startOfDay(),
        };
    }

    protected function periodKey(string $period): string
    {
        return match ($period) {
            'year' => Carbon::now()->format('Y'),
            'month' => Carbon::now()->format('Y-m'),
            'day' => Carbon::now()->format('Y-m-d'),
            'none' => '',
            default => throw new InvalidArgumentException(
                "`{$period}` is not a sequence period; expected year, month, day or none."
            ),
        };
    }

    /**
     * Whose counter this is.
     *
     * `false` is the way to ask for the installation-wide counter explicitly;
     * null means "the account in context", and falls back to the global one
     * in the console, where there is no account.
     */
    protected function scope(Account|string|null $account): string
    {
        if ($account instanceof Account) {
            return (string) $account->getKey();
        }

        if (is_string($account)) {
            return $account;
        }

        return Tenant::currentId() ?? Sequence::GLOBAL;
    }
}
