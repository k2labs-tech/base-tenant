<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Connections\ConnectionManager;
use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Models\AccountConnection;
use Base\Tenant\Notifications\ConnectionFailing;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;

/**
 * Ask every provider whether the credentials it was given still work.
 *
 * A credential that expired three weeks ago is usually discovered by a
 * customer noticing that nothing has synced. Checking nightly turns that into
 * a notification the day it happens.
 */
class CheckConnectionsCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:check-connections
                            {--account= : Only this account}
                            {--provider= : Only this provider}';

    protected $description = 'Health-check account connections and notify on failures';

    public function handle(ConnectionManager $connections): int
    {
        if (! Module::enabled(Module::CONNECTIONS)) {
            $this->components->warn('Connections are disabled; nothing to check.');

            return self::SUCCESS;
        }

        $rows = AccountConnection::query()
            ->acrossAccounts()
            ->enabled()
            ->when($this->option('account'), fn ($query, $account) => $query->where('account_id', $account))
            ->when($this->option('provider'), fn ($query, $provider) => $query->where('provider', $provider))
            ->get();

        $failing = 0;

        foreach ($rows as $connection) {
            if (! $connections->supports($connection->provider)) {
                continue;
            }

            $was = $connection->status;

            $result = $connections->check($connection);

            $this->line(sprintf(
                '  <fg=gray>%s</> %s/%s <fg=%s>%s</>',
                $connection->account_id,
                $connection->provider,
                $connection->label,
                $result->isHealthy() ? 'green' : 'yellow',
                $result->status,
            ));

            if ($result->status !== AccountConnection::FAILING) {
                continue;
            }

            $failing++;

            // Only on the transition. A connection that has been failing for a
            // week should not send a notification every night: the first one
            // was the news, and the rest teach people to ignore them.
            if ($was !== AccountConnection::FAILING) {
                $connection->account?->owner?->notify(new ConnectionFailing($connection, $result->message));
            }
        }

        $this->components->info($failing === 0
            ? 'Every connection answered.'
            : "{$failing} connections are failing.");

        return self::SUCCESS;
    }
}
