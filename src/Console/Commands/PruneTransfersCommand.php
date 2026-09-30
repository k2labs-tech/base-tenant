<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Transfer;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;

/**
 * Apply `transfer.retention_days`.
 *
 * The setting was declared from the start and read by nothing, so exports --
 * full copies of an account's data -- stayed in storage for as long as the
 * account lived.
 */
class PruneTransfersCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:prune-transfers {--days= : Keep this many days}';

    protected $description = 'Delete imports and exports older than the retention window, with the files they produced';

    public function handle(): int
    {
        Module::ensure(Module::TRANSFER);

        $days = $this->option('days') ?? config('base-tenant.transfer.retention_days', 30);

        // An empty setting means "keep them", not "keep them for zero days".
        if ($days === null || $days === '') {
            $this->components->info(__('base-tenant::transfer.console.retention_disabled'));

            return self::SUCCESS;
        }

        $days = max(1, (int) $days);

        $this->components->info(__('base-tenant::transfer.console.pruned', [
            'count' => Transfer::prune($days),
            'days' => $days,
        ]));

        return self::SUCCESS;
    }
}
