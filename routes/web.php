<?php

declare(strict_types=1);

use Base\Tenant\Http\Middleware\HasSubscription;
use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Livewire\UserManager;
use Illuminate\Support\Facades\Route;

if (! config('base-tenant.routes.enabled', true)) {
    return;
}

$prefix = config('base-tenant.routes.prefix', '');
$middleware = config('base-tenant.routes.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Public routes - redirect to login or dashboard
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route('base-tenant.dashboard')
            : redirect()->route('base-tenant.login');
    })->name('base-tenant.home');

    // Protected routes
    Route::middleware(config('base-tenant.routes.auth_middleware', ['auth', 'verified', 'base-tenant.subscription']))->group(function () {
        Route::view('dashboard', 'base-tenant::dashboard')->name('base-tenant.dashboard');
        Route::view('profile', 'base-tenant::profile')->name('base-tenant.profile');

        // User management routes
        Route::get('/users', UserManager::class)->name('base-tenant.users.index');
        Route::get('/users/create', EditUser::class)->name('base-tenant.users.create');
        Route::get('/users/{user}/edit', EditUser::class)->name('base-tenant.users.edit');
    });
});
