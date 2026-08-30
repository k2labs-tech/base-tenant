<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send anyone who has not accepted the current terms to accept them.
 *
 * Version and not date: proving somebody agreed is worth nothing without
 * knowing what they agreed to, and terms change.
 */
class EnsureTermsAccepted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Module::enabled(Module::GDPR) || ! auth()->check()) {
            return $next($request);
        }

        $required = (string) config('base-tenant.gdpr.terms_version', '');

        if ($required === '' || auth()->user()->terms_version === $required) {
            return $next($request);
        }

        // Everything needed to get out has to stay reachable, or the user is
        // locked in a loop with no way to accept and no way to leave.
        if ($request->routeIs('base-tenant.terms', 'base-tenant.logout', 'base-tenant.social.*')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('base-tenant::gdpr.terms_required'),
                'terms_version' => $required,
            ], 403);
        }

        return app('router')->has('base-tenant.terms')
            ? redirect()->route('base-tenant.terms')
            : $next($request);
    }
}
