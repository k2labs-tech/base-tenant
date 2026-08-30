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

        // Bypass in non-production if Stripe is not configured
        if (! app()->environment('production') && ! $this->isStripeConfigured()) {
            return $next($request);
        }

        // Check if user has active subscription
        if (! Auth::user()?->account?->hasActiveSubscription()) {
            return redirect()->route('base-tenant.checkout');
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
