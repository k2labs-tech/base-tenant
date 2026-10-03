<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recompute the `storage.bytes` gauge from the files table and the renditions
 * on the disk.
 *
 * The gauge is maintained by increments, and increments can be lost: a
 * finalize that dies between the move and the meter, a file removed straight
 * from the bucket, a restored backup. A weekly recount means a drift is
 * corrected before anyone is billed on it, and the difference is reported
 * rather than silently applied so that a systematic leak is visible.
 */
class ReconcileStorageCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:reconcile-storage
                            {--account= : Only this account}
                            {--dry-run : Report the drift without correcting it}';

    protected $description = 'Recompute the storage gauge from the files table';

    public function handle(): int
    {
        if (! Module::enabled(Module::FILES) || ! Module::enabled(Module::METERING)) {
            $this->components->warn('Files or metering are disabled; nothing to reconcile.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $drifted = 0;

        // Straight to the builder and without the tenant scope: this runs in
        // the console, where there is no account in context, and it has to see
        // every one of them.
        $totals = File::query()
            ->acrossAccounts()
            ->when($this->option('account'), fn ($query, $account) => $query->where('account_id', $account))
            ->groupBy('account_id')
            ->select('account_id', DB::raw('sum(size) as total'))
            ->pluck('total', 'account_id');

        $totals = $this->withRenditions($totals);

        // An account whose files have all been removed still holds a counter,
        // and it has to come down to zero. Reading only the accounts that have
        // files would leave it wherever it was.
        $counters = DB::table('usage_counters')
            ->where('metric', FileStore::METRIC)
            ->pluck('value', 'account_id');

        foreach ($counters->keys()->merge($totals->keys())->unique() as $accountId) {
            $stored = (int) ($counters[$accountId] ?? 0);
            $real = (int) ($totals[$accountId] ?? 0);

            if ($stored === $real) {
                continue;
            }

            $drifted++;

            $this->line(sprintf(
                '  <fg=gray>%s</> %s → %s <fg=yellow>(%+d)</>',
                $accountId,
                number_format($stored),
                number_format($real),
                $real - $stored,
            ));

            if (! $dryRun) {
                Meter::for((string) $accountId)->set(FileStore::METRIC, $real, [
                    'metadata' => ['reconciled' => true, 'was' => $stored],
                ]);
            }
        }

        $this->components->info($drifted === 0
            ? 'Every storage gauge already matches its files.'
            : ($dryRun
                ? "{$drifted} accounts have drifted."
                : "Corrected {$drifted} accounts."));

        return self::SUCCESS;
    }

    /**
     * Add what the renditions occupy, which the table does not record.
     *
     * The gauge counts them when they are generated and `FileStore::delete()`
     * takes them off again; a recount of originals alone would leave every
     * later delete taking off more than the gauge holds. Read from the disk,
     * one request per rendition, and only for files that have any.
     *
     * @param  Collection<array-key, mixed>  $totals
     * @return Collection<array-key, mixed>
     */
    protected function withRenditions(Collection $totals): Collection
    {
        File::query()
            ->acrossAccounts()
            ->when($this->option('account'), fn ($query, $account) => $query->where('account_id', $account))
            ->whereNotNull('variants')
            ->select(['id', 'account_id', 'disk', 'path', 'variants'])
            ->chunkById(500, function ($files) use ($totals): void {
                foreach ($files as $file) {
                    $totals[$file->account_id] = (int) ($totals[$file->account_id] ?? 0) + $file->variantsSize();
                }
            });

        return $totals;
    }
}
