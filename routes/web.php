<?php

declare(strict_types=1);

use Base\Tenant\Facades\Presale;
use Base\Tenant\Http\Controllers\FileController;
use Base\Tenant\Http\Controllers\LangSyncerWebhookController;
use Base\Tenant\Http\Controllers\NotificationController;
use Base\Tenant\Http\Controllers\SuppressionWebhookController;
use Base\Tenant\Livewire\AcceptTerms;
use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Livewire\AccountSettings;
use Base\Tenant\Livewire\ActivityLog;
use Base\Tenant\Livewire\ConnectionManager as ConnectionManagerComponent;
use Base\Tenant\Livewire\DomainManager as DomainManagerComponent;
use Base\Tenant\Livewire\EditAccount;
use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Livewire\FeatureManager;
use Base\Tenant\Livewire\FileLibrary;
use Base\Tenant\Livewire\InvitationManager;
use Base\Tenant\Livewire\LanguageManager as LanguageManagerComponent;
use Base\Tenant\Livewire\NavigationManager;
use Base\Tenant\Livewire\Notifications\Index;
use Base\Tenant\Livewire\RoleManager;
use Base\Tenant\Livewire\TransferManager as TransferManagerComponent;
use Base\Tenant\Livewire\UsageManager;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\Support\Module;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

if (! config('base-tenant.routes.enabled', true)) {
    return;
}

if (Module::enabled(Module::SUPPRESSIONS)) {
    Route::post('webhooks/suppressions/{driver}', SuppressionWebhookController::class)
        ->withoutMiddleware([ValidateCsrfToken::class])
        ->name('base-tenant.webhooks.suppressions');
}

// Outside the group below on purpose: a webhook arrives with no session, no
// account and no CSRF token, and gating it on any of those would fail every
// delivery.
if (Module::enabled(Module::LANGUAGES)) {
    Route::post('webhooks/langsyncer', LangSyncerWebhookController::class)
        ->withoutMiddleware([ValidateCsrfToken::class])
        ->name('base-tenant.webhooks.langsyncer');
}

$prefix = config('base-tenant.routes.prefix', '');
$middleware = config('base-tenant.routes.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function () {
    // Public routes - redirect to login or dashboard
    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route('base-tenant.dashboard');
        }

        // During pre-sale the public front door is the landing page, not the
        // login form: the people arriving do not have an account to log into.
        if (Presale::isOpen()) {
            return response()->view('base-tenant::presale.landing');
        }

        return redirect()->route('base-tenant.login');
    })->name('base-tenant.home');

    // Protected routes
    Route::middleware(config('base-tenant.routes.auth_middleware', ['auth', 'verified', 'base-tenant.subscription']))->group(function () {
        Route::view('dashboard', 'base-tenant::dashboard')->name('base-tenant.dashboard');
        Route::view('profile', 'base-tenant::profile')->name('base-tenant.profile');
        Route::view('upgrade', 'base-tenant::upgrade')->name('base-tenant.upgrade');

        // User management routes
        Route::get('/users', UserManager::class)->name('base-tenant.users.index');
        Route::get('/users/create', EditUser::class)->name('base-tenant.users.create');
        /*Route::get('/users/create', function () {
            dd('create');
        })->name('base-tenant.users.create');*/
        Route::get('/users/{user}/edit', EditUser::class)->name('base-tenant.users.edit');

        // Account management routes
        Route::get('/accounts', AccountManager::class)->name('base-tenant.accounts.index');
        Route::get('/accounts/create', EditAccount::class)->name('base-tenant.accounts.create');
        Route::get('/accounts/{account}/edit', EditAccount::class)->name('base-tenant.accounts.edit');

        // Impersonate routes
        Route::get('/impersonate/leave', function () {
            auth()->user()->leaveImpersonation();

            return redirect()->route('base-tenant.users.index');
        })->name('impersonate.leave');

        // Roles and permissions
        Route::get('/roles', RoleManager::class)->name('base-tenant.roles.index');

        // Navigation
        Route::get('/navigation', NavigationManager::class)->name('base-tenant.menus.index');

        // Feature flags
        Route::get('/features', FeatureManager::class)->name('base-tenant.features.index');

        // Usage against the plan. Registered only when metering is on: the
        // screen reads counters whose table the host may never have migrated.
        if (Module::enabled(Module::METERING)) {
            Route::get('/usage', UsageManager::class)->name('base-tenant.usage');
        }

        // Files. The upload endpoints are JSON and are called by the uploader
        // component; `upload` only exists with the local driver, where it
        // stands in for a signed PUT to the object store.
        if (Module::enabled(Module::FILES)) {
            Route::post('/files/sign', [FileController::class, 'sign'])->name('base-tenant.files.sign');
            Route::put('/files/upload/{key}', [FileController::class, 'upload'])->name('base-tenant.files.upload');
            Route::post('/files/finalize', [FileController::class, 'finalize'])->name('base-tenant.files.finalize');
            Route::get('/files', FileLibrary::class)->name('base-tenant.files.index');
            Route::get('/files/{file}', [FileController::class, 'show'])->name('base-tenant.files.show');
            Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('base-tenant.files.destroy');
        }

        // Domains. A tenant screen: the names belong to the customer.
        if (Module::enabled(Module::DOMAINS)) {
            Route::get('/domains', DomainManagerComponent::class)->name('base-tenant.domains.index');
        }

        // Languages. A system screen: the catalogue belongs to the
        // installation, not to any one account.
        if (Module::enabled(Module::LANGUAGES)) {
            Route::get('/languages', LanguageManagerComponent::class)->name('base-tenant.languages.index');
        }

        // Imports and exports.
        if (Module::enabled(Module::TRANSFER)) {
            Route::get('/transfers', TransferManagerComponent::class)->name('base-tenant.transfers.index');
        }

        // Connections to external services.
        if (Module::enabled(Module::CONNECTIONS)) {
            Route::get('/connections', ConnectionManagerComponent::class)->name('base-tenant.connections.index');
        }

        // Terms re-acceptance.
        if (Module::enabled(Module::GDPR)) {
            Route::get('/terms', AcceptTerms::class)->name('base-tenant.terms');
        }

        // Account settings
        Route::get('/settings', AccountSettings::class)->name('base-tenant.settings.index');

        // Invitations
        Route::get('/invitations', InvitationManager::class)
            ->name('base-tenant.invitations.index');

        // Activity Log
        Route::get('/activity', ActivityLog::class)->name('base-tenant.activity');

        // Notifications
        Route::get('/notifications', Index::class)
            ->name('base-tenant.notifications.index');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
            ->name('base-tenant.notifications.mark-as-read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
            ->name('base-tenant.notifications.mark-all-read');
    });
});
