<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Resolvers;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;

/**
 * Falls back to the account the authenticated user belongs to.
 */
class UserTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Account
    {
        $user = $request->user() ?? auth()->user();

        if (! $user) {
            return null;
        }

        $accountId = $user->last_account_id
            ?? $user->account_id
            ?? $user->accounts()->value('accounts.id');

        if (! $accountId) {
            return null;
        }

        $model = config('base-tenant.models.account', Account::class);

        return $model::find($accountId);
    }
}
