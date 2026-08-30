<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Connections\ConnectionManager;
use Base\Tenant\Connections\Connector;
use Base\Tenant\Connections\HealthCheck;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ConnectionManager for(Account|string|null $account)
 * @method static array<string, Connector> connectors()
 * @method static Connector connector(string $provider)
 * @method static bool supports(string $provider)
 * @method static Collection<int, AccountConnection> all()
 * @method static AccountConnection|null find(string $provider, string $label = 'default')
 * @method static mixed client(string $provider, string $label = 'default')
 * @method static AccountConnection store(string $provider, array $credentials, string $label = 'default', array $metadata = [])
 * @method static void forget(string $provider, string $label = 'default')
 * @method static HealthCheck check(AccountConnection $connection)
 *
 * @see ConnectionManager
 */
class Connection extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ConnectionManager::class;
    }
}
