<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Services\FeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $account = Tenant::current();

        if (! $account) {
            abort(403, __('base-tenant::plans.no_account'));
        }

        if (! FeatureService::accountCan($account, $feature)) {
            if ($request->expectsJson()) {
                abort(403, __('base-tenant::plans.feature_not_available'));
            }

            return redirect()->route('base-tenant.upgrade');
        }

        return $next($request);
    }
}
