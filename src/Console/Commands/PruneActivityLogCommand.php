<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Services\ActivityLogService;
use Illuminate\Console\Command;

class PruneActivityLogCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:prune-activity-log {--days=90}';

    protected $description = 'Prune activity log entries older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = ActivityLogService::pruneOlderThan($days);

        $this->info("Pruned {$deleted} activity log entries older than {$days} days.");

        return Command::SUCCESS;
    }
}
