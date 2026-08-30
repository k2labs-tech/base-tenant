<?php

declare(strict_types=1);

use Base\Tenant\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (! config('base-tenant.routes.enabled', true) || ! config('base-tenant.subscription.enabled', true)) {
    return;
}

$prefix = config('base-tenant.routes.prefix', '');
$middleware = config('base-tenant.routes.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Checkout route (for users without subscription)
    Route::get('checkout', function () {
        $product = config('base-tenant.subscription.default_product');
        $price = config('base-tenant.subscription.default_price');

        if (! $product || ! $price) {
            abort(503, __('base-tenant::subscription.not_configured'));
        }

        $trialDays = config('base-tenant.subscription.trial_days', 14);

        return Auth::user()->account->newSubscription($product, $price)
            ->trialDays($trialDays)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route(config('base-tenant.subscription.success_url', 'base-tenant.checkout.success')),
                'cancel_url' => route(config('base-tenant.subscription.cancel_url', 'base-tenant.checkout.cancel')),
            ]);
    })
        ->name('base-tenant.checkout')
        ->middleware(['auth', 'verified', 'base-tenant.no-subscription']);

    // Protected routes (for users with subscription)
    Route::middleware(['auth', 'verified', 'base-tenant.subscription'])->group(function () {
        Route::get('checkout/success', [CheckoutController::class, 'success'])
            ->name('base-tenant.checkout.success');

        Route::get('checkout/cancel', [CheckoutController::class, 'cancel'])
            ->name('base-tenant.checkout.cancel');

        Route::get('billing', function () {
            $dashboardRoute = config('base-tenant.home_url', 'base-tenant.dashboard');

            return Auth::user()->account->redirectToBillingPortal(route($dashboardRoute));
        })->name('base-tenant.billing');
    });
});
