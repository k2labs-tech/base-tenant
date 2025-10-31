<?php

declare(strict_types=1);

use Base\Tenant\Http\Controllers\Auth\VerifyEmailController;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

if (! config('base-tenant.routes.enabled', true)) {
    return;
}

$prefix = config('base-tenant.routes.prefix', '');
$middleware = config('base-tenant.routes.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Volt::route('register', 'base-tenant::pages.auth.register')
            ->name('base-tenant.register');

        Volt::route('login', 'base-tenant::pages.auth.login')
            ->name('base-tenant.login');

        Volt::route('forgot-password', 'base-tenant::pages.auth.forgot-password')
            ->name('base-tenant.password.request');

        Volt::route('reset-password/{token}', 'base-tenant::pages.auth.reset-password')
            ->name('base-tenant.password.reset');

        Route::get('two-factor-challenge', TwoFactorChallenge::class)
            ->name('base-tenant.two-factor.challenge');
    });

    // Authenticated routes
    Route::middleware('auth')->group(function () {
        Volt::route('verify-email', 'base-tenant::pages.auth.verify-email')
            ->name('base-tenant.verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('base-tenant.verification.verify');

        Volt::route('confirm-password', 'base-tenant::pages.auth.confirm-password')
            ->name('base-tenant.password.confirm');

        Route::post('logout', function () {
            auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect('/');
        })->name('base-tenant.logout');
    });
});
