<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\TenantManager;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Account|null current()
 * @method static string|null currentId()
 * @method static bool check()
 * @method static Account|null set(Account|string|null $account, bool $remember = false)
 * @method static void forget(bool $alsoForgetSession = false)
 * @method static void reset()
 * @method static Account|null resolve()
 * @method static mixed runFor(Account|string $account, Closure $callback)
 * @method static mixed runWithout(Closure $callback)
 * @method static void eachAccount(Closure $callback, int $chunkSize = 100)
 * @method static void onChange(Closure $callback)
 * @method static string cacheKey(string $key)
 * @method static string channel(string $name)
 *
 * @see TenantManager
 */
class Tenant extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantManager::class;
    }
}
