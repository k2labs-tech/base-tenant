<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Domains\DomainManager;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountDomain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static list<string> reservedSubdomains()
 * @method static string normalizeSubdomain(string $subdomain)
 * @method static bool isSubdomainWellFormed(string $subdomain)
 * @method static bool isSubdomainReserved(string $subdomain)
 * @method static bool isSubdomainAvailable(string $subdomain, Account|null $except = null)
 * @method static void claimSubdomain(Account $account, string $subdomain)
 * @method static void releaseSubdomain(Account $account)
 * @method static string|null hostFor(Account $account)
 * @method static string|null urlFor(Account $account)
 * @method static Collection<int, AccountDomain> domainsFor(Account $account)
 * @method static string normalizeHostname(string $hostname)
 * @method static bool isHostnameWellFormed(string $hostname)
 * @method static AccountDomain addDomain(Account $account, string $hostname)
 * @method static void removeDomain(AccountDomain $domain)
 * @method static bool verify(AccountDomain $domain)
 * @method static void makePrimary(AccountDomain $domain)
 * @method static Collection<int, AccountDomain> dueForVerification()
 *
 * @see DomainManager
 */
class Domain extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DomainManager::class;
    }
}
