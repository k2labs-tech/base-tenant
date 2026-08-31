<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Security;
use Base\Tenant\Services\ActivityLogService;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse requests from outside the account's allowed addresses.
 *
 * Two modes on purpose. `warn` records what would have been blocked without
 * blocking it, so an administrator can switch the rule on, look at a day of
 * real traffic, and find the office VPN they forgot. Going straight to
 * `enforce` is how an account locks itself out on a Friday evening.
 */
class EnforceIpAllowlist
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Module::enabled(Module::SECURITY) || ! auth()->check()) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        if (Security::allowsIp($ip)) {
            return $next($request);
        }

        if (Security::warnsOnIp()) {
            ActivityLogService::log(
                action: 'security.ip_would_be_blocked',
                newValues: ['ip' => $ip],
                description: __('base-tenant::security.ip_warn_logged', ['ip' => $ip]),
            );

            return $next($request);
        }

        if (! Security::enforcesIp()) {
            return $next($request);
        }

        // Leaving has to stay possible: somebody blocked from an address they
        // no longer control still needs to be able to end the session.
        if ($request->routeIs('base-tenant.logout')) {
            return $next($request);
        }

        ActivityLogService::log(
            action: 'security.ip_blocked',
            newValues: ['ip' => $ip],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('base-tenant::security.ip_blocked', ['ip' => $ip]),
            ], 403);
        }

        abort(403, __('base-tenant::security.ip_blocked', ['ip' => $ip]));
    }
}
