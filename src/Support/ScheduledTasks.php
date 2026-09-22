<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Base\Tenant\Console\Commands\CheckConnectionsCommand;
use Base\Tenant\Console\Commands\PruneActivityLogCommand;
use Base\Tenant\Console\Commands\PruneSessionsCommand;
use Base\Tenant\Console\Commands\PurgeDeletedCommand;
use Base\Tenant\Console\Commands\ReconcileStorageCommand;
use Base\Tenant\Console\Commands\ReportUsageCommand;
use Base\Tenant\Console\Commands\VerifyDomainsCommand;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The maintenance work the product does on its own, in one place.
 *
 * It lives here, and not in the service provider, because an application that
 * has taken ownership of the code no longer has that provider: the generated
 * one calls this class, which was copied along with everything else. Keeping
 * the table in the provider is how these tasks went missing at eject time,
 * and nothing announces their absence — a retention job that never runs looks
 * exactly like one that ran and found nothing to do.
 */
class ScheduledTasks
{
    public static function register(Schedule $schedule): void
    {
        if (config('base-tenant.activity_log.enabled', true)) {
            $schedule->command(PruneActivityLogCommand::class)->daily();
        }

        if (Module::enabled(Module::METERING)) {
            $schedule->command(ReportUsageCommand::class)->hourly()->withoutOverlapping();
        }

        if (Module::enabled(Module::FILES) && Module::enabled(Module::METERING)) {
            $schedule->command(ReconcileStorageCommand::class)->weekly()->withoutOverlapping();
        }

        if (Module::enabled(Module::CONNECTIONS)) {
            $schedule->command(CheckConnectionsCommand::class)->daily()->withoutOverlapping();
        }

        if (Module::enabled(Module::GDPR)) {
            $schedule->command(PurgeDeletedCommand::class)->daily()->withoutOverlapping();
        }

        // A domain verified once is not verified forever: zones get edited, and
        // a hostname that stopped proving ownership should stop being treated
        // as proof.
        if (Module::enabled(Module::DOMAINS)) {
            $schedule->command(VerifyDomainsCommand::class)->daily()->withoutOverlapping();
        }

        if (Module::enabled(Module::SECURITY) && config('base-tenant.security.sessions.enabled', true)) {
            $schedule->command(PruneSessionsCommand::class)->daily()->withoutOverlapping();
        }
    }
}
