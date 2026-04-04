<?php

declare(strict_types=1);

namespace Base\Tenant;

use Base\Tenant\Console\Commands\InstallCommand;
use Base\Tenant\Console\Commands\SyncRolesCommand;
use Illuminate\Auth\Notifications\ResetPassword;
use Base\Tenant\Http\Middleware\DoesNotHaveSubscription;
use Base\Tenant\Http\Middleware\EnsurePasswordChanged;
use Base\Tenant\Http\Middleware\HasSubscription;
use Base\Tenant\Http\Middleware\SetAccountContext;
use Base\Tenant\Http\Middleware\SetLocale;
use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Livewire\AccountSwitcher;
use Base\Tenant\Livewire\EditAccount;
use Base\Tenant\Livewire\Logout;
use Base\Tenant\Livewire\Alerts\Table as AlertsTable;
use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Livewire\Forms\LoginForm;
use Base\Tenant\Livewire\Preferences;
use Base\Tenant\Livewire\Profile\DeleteUserForm;
use Base\Tenant\Livewire\Profile\UpdatePasswordForm;
use Base\Tenant\Livewire\Profile\UpdateProfileInformationForm;
use Base\Tenant\Livewire\TwoFactorAuthentication;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\View\Components\AppLayout;
use Base\Tenant\View\Components\GuestLayout;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class BaseTenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/base-tenant.php',
            'base-tenant'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'base-tenant');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'base-tenant');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/subscriptions.php');

        $this->publishes([
            __DIR__.'/../config/base-tenant.php' => config_path('base-tenant.php'),
        ], 'base-tenant-config');

        // Views are NOT published to avoid sync issues.
        // Package views are loaded via namespace: base-tenant::xxx
        // For customization, extend Livewire components or use Blade slots.
        // $this->publishes([
        //     __DIR__.'/../resources/views' => resource_path('views/vendor/base-tenant'),
        // ], 'base-tenant-views');

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/base-tenant'),
        ], 'base-tenant-assets');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path(),
        ], 'base-tenant-lang');

        $this->registerMiddleware();
        $this->registerLivewireComponents();
        $this->registerBladeComponents();
        $this->registerCommands();
        $this->configurePasswordReset();
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('base-tenant.subscription', HasSubscription::class);
        $router->aliasMiddleware('base-tenant.no-subscription', DoesNotHaveSubscription::class);
        $router->aliasMiddleware('base-tenant.locale', SetLocale::class);
        $router->aliasMiddleware('base-tenant.password-changed', EnsurePasswordChanged::class);
        $router->aliasMiddleware('base-tenant.account-context', SetAccountContext::class);

        // Always add SetAccountContext to web middleware group
        $router->pushMiddlewareToGroup('web', SetAccountContext::class);

        // Add EnsurePasswordChanged to web middleware group if enabled
        if (config('base-tenant.force_password_change.enabled', false)) {
            $router->pushMiddlewareToGroup('web', EnsurePasswordChanged::class);
        }
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        // Auth components - register with both naming conventions for compatibility
        Livewire::component('base-tenant.auth.login', \Base\Tenant\Livewire\Auth\Login::class);
        Livewire::component('base-tenant.auth.register', \Base\Tenant\Livewire\Auth\Register::class);
        Livewire::component('base-tenant.auth.forgot-password', \Base\Tenant\Livewire\Auth\ForgotPassword::class);
        Livewire::component('base-tenant.auth.reset-password', \Base\Tenant\Livewire\Auth\ResetPassword::class);
        Livewire::component('base-tenant.auth.confirm-password', \Base\Tenant\Livewire\Auth\ConfirmPassword::class);
        Livewire::component('base-tenant.auth.verify-email', \Base\Tenant\Livewire\Auth\VerifyEmail::class);
        Livewire::component('base-tenant.auth.force-password-change', \Base\Tenant\Livewire\Auth\ForcePasswordChange::class);

        // Other components - register with friendly names
        Livewire::component('base-tenant.user-manager', UserManager::class);
        Livewire::component('base-tenant.edit-user', EditUser::class);
        Livewire::component('base-tenant.account-manager', AccountManager::class);
        Livewire::component('base-tenant.account-switcher', AccountSwitcher::class);
        Livewire::component('base-tenant.edit-account', EditAccount::class);
        Livewire::component('base-tenant.two-factor-authentication', TwoFactorAuthentication::class);
        Livewire::component('base-tenant.two-factor-challenge', TwoFactorChallenge::class);
        Livewire::component('base-tenant.logout', Logout::class);
        Livewire::component('base-tenant.alerts.table', AlertsTable::class);
        Livewire::component('base-tenant.forms.login-form', LoginForm::class);
        Livewire::component('base-tenant.notification-bell', \Base\Tenant\Livewire\NotificationBell::class);
        Livewire::component('base-tenant.notifications.index', \Base\Tenant\Livewire\Notifications\Index::class);
        Livewire::component('base-tenant.invite-users', \Base\Tenant\Livewire\InviteUsers::class);

        // Profile components
        Livewire::component('base-tenant.profile.update-profile-information-form', UpdateProfileInformationForm::class);
        Livewire::component('base-tenant.profile.update-password-form', UpdatePasswordForm::class);
        Livewire::component('base-tenant.profile.delete-user-form', DeleteUserForm::class);
        Livewire::component('base-tenant.preferences', Preferences::class);
    }

    /**
     * Register Blade components.
     */
    protected function registerBladeComponents(): void
    {
        Blade::component('base-tenant::app-layout', AppLayout::class);
        Blade::component('base-tenant::guest-layout', GuestLayout::class);

        // Register anonymous components from the package
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components');
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class,
                InstallCommand::class,
            ]);
        }
    }

    /**
     * Configure password reset URL to use base-tenant routes.
     */
    protected function configurePasswordReset(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url(route('base-tenant.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }
}
