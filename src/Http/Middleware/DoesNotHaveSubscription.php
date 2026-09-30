<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Support\Home;
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
        // Admin users bypass subscription check
        if (Auth::user()?->is_admin) {
            return redirect()->to(Home::url());
        }

        // Bypass in non-production if Stripe is not configured
        if (! app()->environment('production') && ! $this->isStripeConfigured()) {
            return redirect()->to(Home::url());
        }

        // If user has an active subscription, redirect to dashboard
        if (Auth::user()?->account?->hasActiveSubscription()) {
            return redirect()->to(Home::url());
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
