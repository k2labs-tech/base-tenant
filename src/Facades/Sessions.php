<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Sessions\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static UserSession|null touch(Request $request, User $user)
 * @method static bool isRevoked(Request $request)
 * @method static Collection<int, UserSession> forUser(User $user)
 * @method static void revoke(UserSession $session)
 * @method static int revokeOthers(User $user, string|null $keepSessionId = null)
 * @method static int revokeAll(User $user)
 * @method static int prune(int $days = 30)
 *
 * @see SessionManager
 */
class Sessions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SessionManager::class;
    }
}
