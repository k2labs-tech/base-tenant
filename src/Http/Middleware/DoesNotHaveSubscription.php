<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DoesNotHaveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $dashboardRoute = config('base-tenant.home_url', 'base-tenant.dashboard');

        // If user has an active subscription, redirect to dashboard
        if (Auth::user()?->account?->hasActiveSubscription()) {
            return redirect()->route($dashboardRoute);
        }

        return $next($request);
    }
}
