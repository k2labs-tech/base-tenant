<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Sessions;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;

/**
 * Drop session records nobody will look at again.
 *
 * They hold an address and a device, so keeping them past their usefulness is
 * a liability rather than an asset.
 */
class PruneSessionsCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:prune-sessions {--days= : Keep this many days}';

    protected $description = 'Delete session records older than the retention window';

    public function handle(): int
    {
        Module::ensure(Module::SECURITY);

        $days = (int) ($this->option('days') ?? config('base-tenant.security.sessions.retention_days', 30));

        $deleted = Sessions::prune($days);

        $this->components->info(__('base-tenant::sessions.console.pruned', [
            'count' => $deleted,
            'days' => $days,
        ]));

        return self::SUCCESS;
    }
}
