<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Resolvers;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;

/**
 * Resolves the account the user last switched to, verifying they still have
 * access to it. A stale or tampered session value is discarded rather than
 * trusted.
 */
class SessionTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Account
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $session = app('session');
        $accountId = $session->get('current_account_id');

        if (! $accountId) {
            return null;
        }

        $user = $request->user() ?? auth()->user();

        if ($user && ! $user->belongsToAccount($accountId)) {
            $session->forget('current_account_id');

            return null;
        }

        $model = config('base-tenant.models.account', Account::class);

        return $model::find($accountId);
    }
}
