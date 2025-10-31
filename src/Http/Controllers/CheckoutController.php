<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class CheckoutController extends Controller
{
    /**
     * Handle successful checkout.
     */
    public function success(Request $request): RedirectResponse
    {
        $route = config('base-tenant.subscription.success_url', 'base-tenant.dashboard');

        return Redirect::route($route)->with('success', __('base-tenant::subscription.payment_successful'));
    }

    /**
     * Handle cancelled checkout.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $route = config('base-tenant.subscription.cancel_url', 'base-tenant.dashboard');

        return Redirect::route($route)->with('error', __('base-tenant::subscription.payment_cancelled'));
    }
}
