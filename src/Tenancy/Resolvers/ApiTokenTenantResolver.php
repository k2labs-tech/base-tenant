<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Resolvers;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;

/**
 * Resolves the account bound to the Sanctum personal access token used for
 * the request, so API calls never depend on a session.
 */
class ApiTokenTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Account
    {
        $user = $request->user() ?? auth()->user();

        if (! $user || ! method_exists($user, 'currentAccessToken')) {
            return null;
        }

        $token = $user->currentAccessToken();

        if (! $token || ! isset($token->account_id) || ! $token->account_id) {
            return null;
        }

        $model = config('base-tenant.models.account', Account::class);

        return $model::find($token->account_id);
    }
}
