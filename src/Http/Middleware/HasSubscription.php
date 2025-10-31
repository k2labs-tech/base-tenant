<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HasSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If subscriptions are disabled, allow all requests
        if (! config('base-tenant.subscription.enabled', true)) {
            return $next($request);
        }

        // Admin users bypass subscription check
        if (Auth::user()?->is_admin) {
            return $next($request);
        }

        // Check if user has active subscription
        if (! Auth::user()?->account?->hasActiveSubscription()) {
            return redirect()->route('base-tenant.checkout');
        }

        return $next($request);
    }
}
