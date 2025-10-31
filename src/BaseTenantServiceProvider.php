<?php

declare(strict_types=1);

namespace Base\Tenant;

use Base\Tenant\Console\Commands\SyncRolesCommand;
use Base\Tenant\Http\Middleware\DoesNotHaveSubscription;
use Base\Tenant\Http\Middleware\HasSubscription;
use Base\Tenant\Http\Middleware\SetLocale;
use Base\Tenant\Livewire\Actions\Logout;
use Base\Tenant\Livewire\Alerts\Table as AlertsTable;
use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Livewire\Forms\LoginForm;
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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/base-tenant'),
        ], 'base-tenant-views');

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/base-tenant'),
        ], 'base-tenant-assets');

        $this->registerMiddleware();
        $this->registerLivewireComponents();
        $this->registerBladeComponents();
        $this->registerCommands();
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
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('base-tenant.user-manager', UserManager::class);
        Livewire::component('base-tenant.edit-user', EditUser::class);
        Livewire::component('base-tenant.two-factor-authentication', TwoFactorAuthentication::class);
        Livewire::component('base-tenant.two-factor-challenge', TwoFactorChallenge::class);
        Livewire::component('base-tenant.logout', Logout::class);
        Livewire::component('base-tenant.alerts.table', AlertsTable::class);
        Livewire::component('base-tenant.forms.login-form', LoginForm::class);
    }

    /**
     * Register Blade components.
     */
    protected function registerBladeComponents(): void
    {
        Blade::component('base-tenant-app-layout', AppLayout::class);
        Blade::component('base-tenant-guest-layout', GuestLayout::class);
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class,
            ]);
        }
    }
}
