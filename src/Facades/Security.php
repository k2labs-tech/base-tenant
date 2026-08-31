<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;
use Base\Tenant\Security\SecurityPolicyManager;
use Base\Tenant\Security\SecurityPolicySettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static SecurityPolicySettings for(Account|null $account = null)
 * @method static bool enabled()
 * @method static bool requiresTwoFactor(Account|null $account = null)
 * @method static CarbonInterface twoFactorDeadlineFor(User $user, Account|null $account = null)
 * @method static bool twoFactorIsOverdue(User $user, Account|null $account = null)
 * @method static void startTwoFactorClock(Account $account)
 * @method static array<int, string> normalizeDomains(array<int, string> $domains)
 * @method static bool allowsEmail(string $email, Account|null $account = null)
 * @method static bool allowsIp(string $ip, Account|null $account = null)
 * @method static bool enforcesIp(Account|null $account = null)
 * @method static bool warnsOnIp(Account|null $account = null)
 * @method static int sessionTimeoutMinutes(Account|null $account = null)
 * @method static SecurityPolicySettings update(Account $account, array<string, mixed> $values)
 *
 * @see SecurityPolicyManager
 */
class Security extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SecurityPolicyManager::class;
    }
}
