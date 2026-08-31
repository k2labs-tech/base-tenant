<?php

declare(strict_types=1);

namespace Base\Tenant;

use Base\Tenant\Connections\ConnectionManager;
use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Console\Commands\CheckConnectionsCommand;
use Base\Tenant\Console\Commands\EjectCommand;
use Base\Tenant\Console\Commands\ExportUserDataCommand;
use Base\Tenant\Console\Commands\ImportSuppressionsCommand;
use Base\Tenant\Console\Commands\InstallCommand;
use Base\Tenant\Console\Commands\LangPullCommand;
use Base\Tenant\Console\Commands\LangPushCommand;
use Base\Tenant\Console\Commands\LangStatusCommand;
use Base\Tenant\Console\Commands\MakeModuleCommand;
use Base\Tenant\Console\Commands\PresaleOpenCommand;
use Base\Tenant\Console\Commands\PruneActivityLogCommand;
use Base\Tenant\Console\Commands\PublishAgentDocsCommand;
use Base\Tenant\Console\Commands\PurgeDeletedCommand;
use Base\Tenant\Console\Commands\ReconcileStorageCommand;
use Base\Tenant\Console\Commands\ReportUsageCommand;
use Base\Tenant\Console\Commands\ScaffoldCommand;
use Base\Tenant\Console\Commands\SyncMenusCommand;
use Base\Tenant\Console\Commands\SyncRolesCommand;
use Base\Tenant\Console\Commands\VerifyDomainsCommand;
use Base\Tenant\Domains\Contracts\DnsLookup;
use Base\Tenant\Domains\DomainManager;
use Base\Tenant\Domains\DomainVerifier;
use Base\Tenant\Domains\SystemDnsLookup;
use Base\Tenant\Facades\Feature as FeatureFacade;
use Base\Tenant\Facades\Meter as MeterFacade;
use Base\Tenant\Features\FeatureManager;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Files\ImageVariants;
use Base\Tenant\Gdpr\DataExportService;
use Base\Tenant\Http\Middleware\DoesNotHaveSubscription;
use Base\Tenant\Http\Middleware\EnforceIpAllowlist;
use Base\Tenant\Http\Middleware\EnforceSessionTimeout;
use Base\Tenant\Http\Middleware\EnforceTwoFactor;
use Base\Tenant\Http\Middleware\EnsurePasswordChanged;
use Base\Tenant\Http\Middleware\EnsureTermsAccepted;
use Base\Tenant\Http\Middleware\EnsureWithinUsageLimit;
use Base\Tenant\Http\Middleware\HasFeature;
use Base\Tenant\Http\Middleware\HasSubscription;
use Base\Tenant\Http\Middleware\SetAccountContext;
use Base\Tenant\Http\Middleware\SetLocale;
use Base\Tenant\Languages\LangFileWriter;
use Base\Tenant\Languages\LangSyncerClient;
use Base\Tenant\Languages\LanguageManager;
use Base\Tenant\Languages\TranslationCoverage;
use Base\Tenant\Livewire\AcceptTerms;
use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Livewire\AccountSettings;
use Base\Tenant\Livewire\AccountSwitcher;
use Base\Tenant\Livewire\ActivityLog;
use Base\Tenant\Livewire\Alerts\Table as AlertsTable;
use Base\Tenant\Livewire\Auth\ConfirmPassword;
use Base\Tenant\Livewire\Auth\ForcePasswordChange;
use Base\Tenant\Livewire\Auth\ForgotPassword;
use Base\Tenant\Livewire\Auth\Login;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\Auth\VerifyEmail;
use Base\Tenant\Livewire\ConnectionManager as ConnectionManagerComponent;
use Base\Tenant\Livewire\DomainManager as DomainManagerComponent;
use Base\Tenant\Livewire\EditAccount;
use Base\Tenant\Livewire\EditUser;
use Base\Tenant\Livewire\FeatureManager as FeatureManagerComponent;
use Base\Tenant\Livewire\FileLibrary;
use Base\Tenant\Livewire\Files\Gallery as FilesGallery;
use Base\Tenant\Livewire\Files\Uploader as FilesUploader;
use Base\Tenant\Livewire\Files\UsageBadge as FilesUsageBadge;
use Base\Tenant\Livewire\Forms\LoginForm;
use Base\Tenant\Livewire\InvitationManager;
use Base\Tenant\Livewire\LanguageManager as LanguageManagerComponent;
use Base\Tenant\Livewire\Logout;
use Base\Tenant\Livewire\NavigationManager;
use Base\Tenant\Livewire\NotificationBell;
use Base\Tenant\Livewire\Notifications\Index;
use Base\Tenant\Livewire\Onboarding\Checklist as OnboardingChecklist;
use Base\Tenant\Livewire\Preferences;
use Base\Tenant\Livewire\Presale\PricingTable;
use Base\Tenant\Livewire\Presale\WaitlistForm;
use Base\Tenant\Livewire\Profile\ConnectedAccounts;
use Base\Tenant\Livewire\Profile\DeleteUserForm;
use Base\Tenant\Livewire\Profile\UpdatePasswordForm;
use Base\Tenant\Livewire\Profile\UpdateProfileInformationForm;
use Base\Tenant\Livewire\RoleManager;
use Base\Tenant\Livewire\SecurityPolicyManager as SecurityPolicyManagerComponent;
use Base\Tenant\Livewire\TransferManager as TransferManagerComponent;
use Base\Tenant\Livewire\TwoFactorAuthentication;
use Base\Tenant\Livewire\TwoFactorChallenge;
use Base\Tenant\Livewire\UsageManager;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\Menu\DefaultMenus;
use Base\Tenant\Menu\MenuManager;
use Base\Tenant\Metering\MeterManager;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Metering\UsageStore;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Onboarding\OnboardingManager;
use Base\Tenant\Policies\AccountPolicy;
use Base\Tenant\Policies\RolePolicy;
use Base\Tenant\Policies\UserInvitePolicy;
use Base\Tenant\Policies\UserPolicy;
use Base\Tenant\Presale\PresaleManager;
use Base\Tenant\Security\SecurityPolicyManager;
use Base\Tenant\Sequences\SequenceManager;
use Base\Tenant\Settings\SettingsManager;
use Base\Tenant\Social\SocialLoginService;
use Base\Tenant\Support\Module;
use Base\Tenant\Suppressions\BlockSuppressedRecipients;
use Base\Tenant\Suppressions\SuppressionManager;
use Base\Tenant\Tenancy\QueueTenancy;
use Base\Tenant\Tenancy\TenantManager;
use Base\Tenant\Tenancy\TenantTeamResolver;
use Base\Tenant\Transfer\TransferManager;
use Base\Tenant\View\Components\AppLayout;
use Base\Tenant\View\Components\GuestLayout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class BaseTenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/base-tenant.php',
            'base-tenant'
        );

        $this->configurePermissionPackage();

        foreach (static::singletons() as $singleton) {
            $this->app->singleton($singleton);
        }

        // Takes a path, so it cannot be autowired from the class name alone.
        $this->app->singleton(LangFileWriter::class, fn (): LangFileWriter => LangFileWriter::forApplication());

        // An interface, so it cannot be autowired: a test binds a fake and an
        // installation behind a split-horizon resolver binds its own.
        $this->app->bind(DnsLookup::class, SystemDnsLookup::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerSchedule();

        // Once the code has been copied into the application, the generated
        // TenancyServiceProvider owns it. Registering routes, views and
        // components from here as well would give every route two definitions.
        if ($this->hasBeenScaffolded()) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'base-tenant');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'base-tenant');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/subscriptions.php');

        $this->bootTenancy();
        $this->registerAuthorization();
        $this->registerMenus();
        $this->registerMiddleware();
        $this->registerLivewireComponents();
        $this->registerBladeComponents();
        $this->configurePasswordReset();
        $this->configureEmailVerification();
        $this->registerSocialProviders();
        $this->registerSuppressionGuard();
    }

    /**
     * The application has its own copy of the code and is running on it.
     */
    protected function hasBeenScaffolded(): bool
    {
        return in_array(config('base-tenant.installation_state'), ['scaffolded', 'ejected'], true);
    }

    protected function registerPublishing(): void
    {
        $this->registerCommands();

        $this->publishes([
            __DIR__.'/../config/base-tenant.php' => config_path('base-tenant.php'),
        ], 'base-tenant-config');

        // Views are NOT published to avoid sync issues.
        // Package views are loaded via namespace: base-tenant::xxx
        // For customization, extend Livewire components or use Blade slots.

        // No hay assets que publicar: el paquete no compila nada. Los estilos
        // los construye la aplicación anfitriona, que importa el tema y apunta
        // sus `@source` a estas vistas. Ver `docs/FRONTEND.md`.

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path(),
        ], 'base-tenant-lang');

        // The module generator's templates, so a project can shape what
        // `k2labs-base:make-module` produces to its own house style.
        $this->publishes([
            __DIR__.'/../stubs/module' => base_path('stubs/base-tenant/module'),
        ], 'base-tenant-stubs');
    }

    /**
     * Point spatie/laravel-permission at this package's models and make
     * `account_id` its team column, so every role assignment is scoped to a
     * tenant without the host application configuring anything.
     */
    protected function configurePermissionPackage(): void
    {
        config([
            'permission.teams' => true,
            'permission.team_resolver' => TenantTeamResolver::class,
            'permission.column_names.team_foreign_key' => 'account_id',
            'permission.column_names.model_morph_key' => 'model_id',
            'permission.models.role' => config('base-tenant.models.role', Role::class),
            'permission.models.permission' => config('base-tenant.models.permission', Permission::class),
        ]);
    }

    /**
     * Carry the tenant across the queue boundary. The permission layer reads
     * the tenant through TenantTeamResolver, so it needs no wiring here.
     */
    protected function bootTenancy(): void
    {
        if (config('base-tenant.tenancy.propagate_to_queue', true)) {
            QueueTenancy::register($this->app->make(Dispatcher::class));
        }
    }

    /**
     * Model configuration key mapped to its policy.
     *
     * Exposed so `k2labs-base:scaffold` can render the same registrations into
     * the application without keeping a second copy that drifts.
     *
     * @return array<string, class-string>
     */
    public static function policies(): array
    {
        return [
            'user' => UserPolicy::class,
            'account' => AccountPolicy::class,
            'role' => RolePolicy::class,
            'user_invite' => UserInvitePolicy::class,
        ];
    }

    /** @return array<string, class-string> */
    public static function middlewareAliases(): array
    {
        return [
            'base-tenant.subscription' => HasSubscription::class,
            'base-tenant.no-subscription' => DoesNotHaveSubscription::class,
            'base-tenant.locale' => SetLocale::class,
            'base-tenant.password-changed' => EnsurePasswordChanged::class,
            'base-tenant.account-context' => SetAccountContext::class,
            'base-tenant.feature' => HasFeature::class,
            'base-tenant.terms' => EnsureTermsAccepted::class,
            'base-tenant.metered' => EnsureWithinUsageLimit::class,
            'base-tenant.two-factor' => EnforceTwoFactor::class,
            'base-tenant.ip-allowlist' => EnforceIpAllowlist::class,
            'base-tenant.session-timeout' => EnforceSessionTimeout::class,
        ];
    }

    /** @return array<string, class-string> */
    public static function singletons(): array
    {
        return [
            TenantManager::class,
            DomainManager::class,
            DomainVerifier::class,
            SecurityPolicyManager::class,
            FeatureManager::class,
            FileStore::class,
            LanguageManager::class,
            SequenceManager::class,
            SocialLoginService::class,
            ConnectionManager::class,
            TransferManager::class,
            WebhookManager::class,
            LangSyncerClient::class,
            TranslationCoverage::class,
            ImageVariants::class,
            MeterManager::class,
            OnboardingManager::class,
            DataExportService::class,
            PresaleManager::class,
            SuppressionManager::class,
            MetricRegistry::class,
            UsageStore::class,
            MenuManager::class,
            SettingsManager::class,
        ];
    }

    protected function registerAuthorization(): void
    {
        $defaults = [
            'user' => User::class,
            'account' => Account::class,
            'role' => Role::class,
            'user_invite' => UserInvite::class,
        ];

        foreach (static::policies() as $key => $policy) {
            Gate::policy(config("base-tenant.models.{$key}", $defaults[$key]), $policy);
        }

        Gate::before(function ($user): ?bool {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }

    protected function registerMenus(): void
    {
        if (! config('base-tenant.menu.enabled', true)) {
            return;
        }

        DefaultMenus::register();

        $this->app->make(MenuManager::class)->bootBadgeResolvers();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        foreach (static::middlewareAliases() as $alias => $middleware) {
            $router->aliasMiddleware($alias, $middleware);
        }

        $router->pushMiddlewareToGroup('web', SetAccountContext::class);

        if (config('base-tenant.force_password_change.enabled', false)) {
            $router->pushMiddlewareToGroup('web', EnsurePasswordChanged::class);
        }

        // Solo con una versión puesta: sin ella el middleware no tendría nada
        // contra lo que comparar y correría en cada petición para no hacer nada.
        if (Module::enabled(Module::GDPR) && config('base-tenant.gdpr.terms_version')) {
            $router->pushMiddlewareToGroup('web', EnsureTermsAccepted::class);
        }
    }

    /** @return array<string, class-string> */
    public static function livewireComponents(): array
    {
        return [
            'base-tenant.auth.login' => Login::class,
            'base-tenant.auth.register' => Register::class,
            'base-tenant.auth.forgot-password' => ForgotPassword::class,
            'base-tenant.auth.reset-password' => \Base\Tenant\Livewire\Auth\ResetPassword::class,
            'base-tenant.auth.confirm-password' => ConfirmPassword::class,
            'base-tenant.auth.verify-email' => VerifyEmail::class,
            'base-tenant.auth.force-password-change' => ForcePasswordChange::class,
            'base-tenant.file-library' => FileLibrary::class,
            'base-tenant.accept-terms' => AcceptTerms::class,
            'base-tenant.presale.pricing-table' => PricingTable::class,
            'base-tenant.presale.waitlist-form' => WaitlistForm::class,
            'base-tenant.connection-manager' => ConnectionManagerComponent::class,
            'base-tenant.domain-manager' => DomainManagerComponent::class,
            'base-tenant.security-policy-manager' => SecurityPolicyManagerComponent::class,
            'base-tenant.transfer-manager' => TransferManagerComponent::class,
            'base-tenant.language-manager' => LanguageManagerComponent::class,
            'base-tenant.files.uploader' => FilesUploader::class,
            'base-tenant.files.gallery' => FilesGallery::class,
            'base-tenant.files.usage-badge' => FilesUsageBadge::class,
            'base-tenant.usage-manager' => UsageManager::class,
            'base-tenant.user-manager' => UserManager::class,
            'base-tenant.edit-user' => EditUser::class,
            'base-tenant.account-manager' => AccountManager::class,
            'base-tenant.account-switcher' => AccountSwitcher::class,
            'base-tenant.edit-account' => EditAccount::class,
            'base-tenant.role-manager' => RoleManager::class,
            'base-tenant.navigation-manager' => NavigationManager::class,
            'base-tenant.feature-manager' => FeatureManagerComponent::class,
            'base-tenant.account-settings' => AccountSettings::class,
            'base-tenant.two-factor-authentication' => TwoFactorAuthentication::class,
            'base-tenant.two-factor-challenge' => TwoFactorChallenge::class,
            'base-tenant.logout' => Logout::class,
            'base-tenant.alerts.table' => AlertsTable::class,
            'base-tenant.forms.login-form' => LoginForm::class,
            'base-tenant.notification-bell' => NotificationBell::class,
            'base-tenant.onboarding.checklist' => OnboardingChecklist::class,
            'base-tenant.notifications.index' => Index::class,
            'base-tenant.activity-log' => ActivityLog::class,
            'base-tenant.invitation-manager' => InvitationManager::class,
            'base-tenant.profile.update-profile-information-form' => UpdateProfileInformationForm::class,
            'base-tenant.profile.update-password-form' => UpdatePasswordForm::class,
            'base-tenant.profile.connected-accounts' => ConnectedAccounts::class,
            'base-tenant.profile.delete-user-form' => DeleteUserForm::class,
            'base-tenant.preferences' => Preferences::class,
        ];
    }

    /** @return array<string, class-string> */
    public static function bladeComponents(): array
    {
        return [
            'base-tenant::app-layout' => AppLayout::class,
            'base-tenant::guest-layout' => GuestLayout::class,
        ];
    }

    protected function registerLivewireComponents(): void
    {
        foreach (static::livewireComponents() as $name => $class) {
            Livewire::component($name, $class);
        }
    }

    protected function registerBladeComponents(): void
    {
        foreach (static::bladeComponents() as $alias => $class) {
            Blade::component($alias, $class);
        }

        // No anonymous component path is registered on purpose. The view
        // namespace already resolves `<x-base-tenant::application-logo>`, while
        // a registered path answers the bare `<x-application-logo>` too -- a
        // name the host application may want for itself, and one that stops
        // resolving the moment the code is copied into `views/components/tenant`.
        //
        // `application-logo` is the only anonymous component left: the redesign
        // moved every field, button and modal onto Flux primitives.

        Blade::if('feature', fn (string $feature): bool => FeatureFacade::active($feature));

        // @withinlimit('checks.run') ... @endwithinlimit
        //
        // Takes a metric, not a feature: what the screen needs to know is
        // whether there is room left, and only the metric can answer that.
        // With metering off there is no ceiling, so everything is within it.
        Blade::if('withinlimit', fn (string $metric, int $by = 1): bool => Module::enabled(Module::METERING)
            ? ! MeterFacade::wouldExceed($metric, $by)
            : true);
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SyncRolesCommand::class,
            SyncMenusCommand::class,
            InstallCommand::class,
            ScaffoldCommand::class,
            EjectCommand::class,
            CheckConnectionsCommand::class,
            ExportUserDataCommand::class,
            ImportSuppressionsCommand::class,
            LangPullCommand::class,
            LangPushCommand::class,
            LangStatusCommand::class,
            MakeModuleCommand::class,
            PruneActivityLogCommand::class,
            PublishAgentDocsCommand::class,
            ReportUsageCommand::class,
            PresaleOpenCommand::class,
            PurgeDeletedCommand::class,
            ReconcileStorageCommand::class,
            VerifyDomainsCommand::class,
        ]);
    }

    /**
     * Put the package's recurring work on the application's scheduler.
     *
     * Each task is gated by its own module, so switching a module off also
     * stops the job that feeds it -- otherwise a disabled module would keep a
     * nightly command querying tables the host never migrated.
     */
    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('base-tenant.activity_log.enabled', true)) {
                $schedule->command(PruneActivityLogCommand::class)->daily();
            }

            if (Module::enabled(Module::METERING)) {
                $schedule->command(ReportUsageCommand::class)->hourly()->withoutOverlapping();
            }

            if (Module::enabled(Module::FILES) && Module::enabled(Module::METERING)) {
                $schedule->command(ReconcileStorageCommand::class)->weekly()->withoutOverlapping();
            }

            if (Module::enabled(Module::CONNECTIONS)) {
                $schedule->command(CheckConnectionsCommand::class)->daily()->withoutOverlapping();
            }

            if (Module::enabled(Module::GDPR)) {
                $schedule->command(PurgeDeletedCommand::class)->daily()->withoutOverlapping();
            }

            // A domain verified once is not verified forever: zones get
            // edited, and a hostname that stopped proving ownership should
            // stop being treated as proof.
            if (Module::enabled(Module::DOMAINS)) {
                $schedule->command(VerifyDomainsCommand::class)->daily()->withoutOverlapping();
            }
        });
    }

    /**
     * Hand Socialite the drivers it does not ship with.
     *
     * Microsoft Entra ID comes from socialiteproviders/microsoft, which
     * registers itself through an event. Guarded by `class_exists` so a host
     * that does not want that provider is not forced to install it.
     */
    protected function registerSocialProviders(): void
    {
        if (! Module::enabled(Module::SOCIAL)) {
            return;
        }

        if (! class_exists(SocialiteWasCalled::class) || ! class_exists(MicrosoftExtendSocialite::class)) {
            return;
        }

        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
    }

    /**
     * Stop any message addressed to a suppressed address.
     *
     * On the event and not inside a Mailable, so it covers the package's mail,
     * the application's and anything a library sends: a guard that has to be
     * remembered at each call site will be forgotten at one of them.
     */
    protected function registerSuppressionGuard(): void
    {
        if (! Module::enabled(Module::SUPPRESSIONS)) {
            return;
        }

        Event::listen(MessageSending::class, [BlockSuppressedRecipients::class, 'handle']);
    }

    protected function configurePasswordReset(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            return url(route('base-tenant.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }

    /**
     * Point Laravel's own verification notification at the package's route.
     *
     * Without this the notification signs its link against `verification.verify`,
     * a name the package never registers, and every unverified user hits a 500
     * the moment the screen tries to send the mail. The parameters have to stay
     * exactly the ones `EmailVerificationRequest` compares against -- the key
     * and the sha1 of the address -- or the link would swap that 500 for a 403.
     */
    protected function configureEmailVerification(): void
    {
        VerifyEmailNotification::createUrlUsing(function ($notifiable): string {
            return URL::temporarySignedRoute(
                'base-tenant.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });
    }
}
