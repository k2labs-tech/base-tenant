<?php

declare(strict_types=1);

use Base\Tenant\Http\Controllers\Auth\SocialLoginController;
use Base\Tenant\Http\Controllers\Auth\VerifyEmailController;
use Base\Tenant\Http\Controllers\InvitationAcceptController;
use Base\Tenant\Livewire\Auth\ConfirmPassword;
use Base\Tenant\Livewire\Auth\ForcePasswordChange;
use Base\Tenant\Livewire\Auth\ForgotPassword;
use Base\Tenant\Livewire\Auth\Login;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\Auth\ResetPassword;
use Base\Tenant\Livewire\Auth\VerifyEmail;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Base\Tenant\Support\Module;
use Illuminate\Support\Facades\Route;

if (! config('base-tenant.routes.enabled', true)) {
    return;
}

$prefix = config('base-tenant.routes.prefix', '');
$middleware = config('base-tenant.routes.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('register', Register::class)
            ->name('base-tenant.register');

        Route::get('login', Login::class)
            ->name('base-tenant.login');

        Route::get('forgot-password', ForgotPassword::class)
            ->name('base-tenant.password.request');

        Route::get('reset-password/{token}', ResetPassword::class)
            ->name('base-tenant.password.reset');

        Route::get('two-factor-challenge', TwoFactorChallenge::class)
            ->name('base-tenant.two-factor.challenge');
    });

    // Authenticated routes
    Route::middleware('auth')->group(function () {
        Route::get('verify-email', VerifyEmail::class)
            ->name('base-tenant.verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('base-tenant.verification.verify');

        Route::get('confirm-password', ConfirmPassword::class)
            ->name('base-tenant.password.confirm');

        // Force password change route
        Route::get('password/change', ForcePasswordChange::class)
            ->name('base-tenant.password.change');

        Route::post('logout', function () {
            auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect('/');
        })->name('base-tenant.logout');
    });

    // Social login. The same pair of routes serves signing in and linking
    // from the profile; which one happens is decided by whether there is a
    // session.
    if (Module::enabled(Module::SOCIAL)) {
        Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
            ->name('base-tenant.social.redirect');

        Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
            ->name('base-tenant.social.callback');
    }

    // Invitation acceptance (public - before auth check)
    Route::get('invitations/accept/{token}', InvitationAcceptController::class)
        ->name('base-tenant.invitations.accept');
});
