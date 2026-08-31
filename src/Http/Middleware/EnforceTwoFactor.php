<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Security;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send anyone past their grace period to set up a second factor.
 *
 * The account's administrator decided this, not the platform, which is the
 * whole point of the rule living per tenant.
 */
class EnforceTwoFactor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Module::enabled(Module::SECURITY) || ! auth()->check()) {
            return $next($request);
        }

        if (! Security::twoFactorIsOverdue(auth()->user())) {
            return $next($request);
        }

        // Everything needed to comply, and to leave, has to stay reachable or
        // the user is in a loop with no way out: the profile screen is where
        // the second factor is set up.
        if ($request->routeIs(
            'base-tenant.profile',
            'base-tenant.logout',
            'base-tenant.two-factor.*',
            'base-tenant.social.*',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('base-tenant::security.two_factor_required'),
            ], 403);
        }

        return redirect()
            ->route('base-tenant.profile')
            ->with('status', __('base-tenant::security.two_factor_required'));
    }
}
