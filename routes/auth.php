<?php

declare(strict_types=1);

use Base\Tenant\Http\Controllers\Auth\MagicLinkController;
use Base\Tenant\Http\Controllers\Auth\PasskeyController;
use Base\Tenant\Http\Controllers\Auth\SocialLoginController;
use Base\Tenant\Http\Controllers\Auth\VerifyEmailController;
use Base\Tenant\Http\Controllers\InvitationAcceptController;
use Base\Tenant\Livewire\Auth\ConfirmPassword;
use Base\Tenant\Livewire\Auth\ForcePasswordChange;
use Base\Tenant\Livewire\Auth\ForgotPassword;
use Base\Tenant\Livewire\Auth\Login;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\Auth\RequestMagicLink;
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

        if (Module::enabled(Module::PASSWORDLESS)) {
            Route::get('magic-link', RequestMagicLink::class)
                ->name('base-tenant.magic-link.request');

            // Throttled at the route as well as in the manager: the manager's
            // limit is per address, this one is the blunt ceiling on anybody
            // hammering the endpoint with tokens.
            //
            // Two routes because the GET from the email is followed by mail
            // scanners before the person clicks; it only shows a button, and
            // the POST behind it is what spends the token.
            Route::get('magic-link/{token}', [MagicLinkController::class, 'show'])
                ->middleware('throttle:10,1')
                ->name('base-tenant.magic-link.show');

            Route::post('magic-link/{token}', [MagicLinkController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('base-tenant.magic-link.consume');

            Route::post('passkeys/login/options', [PasskeyController::class, 'loginOptions'])
                ->middleware('throttle:20,1')
                ->name('base-tenant.passkeys.login-options');

            Route::post('passkeys/login', [PasskeyController::class, 'login'])
                ->middleware('throttle:20,1')
                ->name('base-tenant.passkeys.login');
        }
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

        if (Module::enabled(Module::PASSWORDLESS)) {
            Route::post('passkeys/options', [PasskeyController::class, 'registerOptions'])
                ->name('base-tenant.passkeys.register-options');

            Route::post('passkeys', [PasskeyController::class, 'register'])
                ->name('base-tenant.passkeys.register');
        }

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
