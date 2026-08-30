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

        // Admin users bypass subscription check
        if (Auth::user()?->is_admin) {
            return redirect()->route($dashboardRoute);
        }

        // Bypass in non-production if Stripe is not configured
        if (! app()->environment('production') && ! $this->isStripeConfigured()) {
            return redirect()->route($dashboardRoute);
        }

        // If user has an active subscription, redirect to dashboard
        if (Auth::user()?->account?->hasActiveSubscription()) {
            return redirect()->route($dashboardRoute);
        }

        return $next($request);
    }

    protected function isStripeConfigured(): bool
    {
        return config('cashier.key')
            && config('cashier.secret')
            && config('base-tenant.subscription.default_product')
            && config('base-tenant.subscription.default_price');
    }
}
