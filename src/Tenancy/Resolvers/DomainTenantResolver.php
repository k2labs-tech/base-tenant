<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Resolvers;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountDomain;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the account from the request host: first a custom domain the
 * customer has proved they control, then the legacy `accounts.domain` column,
 * then a subdomain of one of the central domains.
 */
class DomainTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Account
    {
        $host = mb_strtolower($request->getHost());
        $model = config('base-tenant.models.account', Account::class);

        $account = $this->fromCustomDomain($host);

        if ($account) {
            return $account;
        }

        if (Schema::hasColumn('accounts', 'domain')) {
            $account = $model::query()->where('domain', $host)->first();

            if ($account) {
                return $account;
            }
        }

        $subdomain = $this->subdomain($host);

        if (! $subdomain || ! Schema::hasColumn('accounts', 'subdomain')) {
            return null;
        }

        return $model::query()->where('subdomain', $subdomain)->first();
    }

    /**
     * A registered custom domain, and only if it is verified.
     *
     * The `verified()` scope is the guard of the whole capability: an
     * unverified row is a hostname somebody typed into a form, not a hostname
     * they own. Serving it would let anyone who can point DNS at this product
     * be served as the account that claimed the name.
     */
    protected function fromCustomDomain(string $host): ?Account
    {
        if (! Schema::hasTable('account_domains')) {
            return null;
        }

        $domain = AccountDomain::query()
            ->verified()
            ->where('hostname', $host)
            ->first();

        return $domain?->account;
    }

    protected function subdomain(string $host): ?string
    {
        foreach (config('base-tenant.tenancy.central_domains', []) as $central) {
            if ($host === $central || ! str_ends_with($host, ".{$central}")) {
                continue;
            }

            $subdomain = substr($host, 0, -(strlen($central) + 1));

            return $subdomain === '' ? null : $subdomain;
        }

        return null;
    }
}
