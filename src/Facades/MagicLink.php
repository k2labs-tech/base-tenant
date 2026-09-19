<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\User;
use Base\Tenant\Passwordless\MagicLinkManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled()
 * @method static int ttlMinutes()
 * @method static void request(string $email, Request|null $request = null)
 * @method static bool tooManyRequests(string $email, Request|null $request = null)
 * @method static void recordRequest(string $email, Request|null $request = null)
 * @method static int secondsUntilRetry(string $email)
 * @method static User|null consume(string $token)
 * @method static bool isUsable(string $token)
 * @method static int prune(int $days = 7)
 *
 * @see MagicLinkManager
 */
class MagicLink extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MagicLinkManager::class;
    }
}
