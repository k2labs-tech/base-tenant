<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Resolvers;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the account from the request host, either by exact domain match or
 * by subdomain against the configured central domains.
 */
class DomainTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Account
    {
        if (! Schema::hasColumn('accounts', 'domain')) {
            return null;
        }

        $host = $request->getHost();
        $model = config('base-tenant.models.account', Account::class);

        $account = $model::query()->where('domain', $host)->first();

        if ($account) {
            return $account;
        }

        $subdomain = $this->subdomain($host);

        if (! $subdomain) {
            return null;
        }

        return $model::query()->where('subdomain', $subdomain)->first();
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
