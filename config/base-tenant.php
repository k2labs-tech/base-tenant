<?php

use Base\Tenant\Gdpr\Exporters\ActivityExporter;
use Base\Tenant\Gdpr\Exporters\ProfileExporter;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Onboarding\Checks\ProfileCompleted;
use Base\Tenant\Onboarding\Checks\TeamInvited;
use Base\Tenant\Suppressions\MailgunDriver;
use Base\Tenant\Tenancy\Resolvers\ApiTokenTenantResolver;
use Base\Tenant\Tenancy\Resolvers\DomainTenantResolver;
use Base\Tenant\Tenancy\Resolvers\SessionTenantResolver;
use Base\Tenant\Tenancy\Resolvers\UserTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Base Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Base Tenant package including multi-team
    | support, subscription settings, and customizable role definitions.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Multi-Team Support
    |--------------------------------------------------------------------------
    |
    | Enable or disable multi-team functionality. When enabled, users can
    | belong to multiple accounts/teams.
    |
    */

    'multi_team' => env('BASE_TENANT_MULTI_TEAM', false),

    /*
    |--------------------------------------------------------------------------
    | Installation State
    |--------------------------------------------------------------------------
    |
    | Tracks how far this project has moved from depending on the package to
    | owning the code outright. The commands read it and refuse to run out of
    | order; you should not normally edit it by hand.
    |
    |   fresh       Nothing installed yet
    |   installed   Running on the package
    |   scaffolded  The code has been copied into the application, which now
    |               owns routes, views and components. The package is still
    |               present but stands down.
    |   ejected     The package has been removed
    |
    */

    'installation_state' => 'installed',

    /*
    |--------------------------------------------------------------------------
    | Platform Administrator
    |--------------------------------------------------------------------------
    |
    | The staff account AdminUserSeeder creates so a freshly migrated database
    | can be signed into. Change these before seeding anywhere real.
    |
    */

    'admin' => [
        'name' => env('BASE_TENANT_ADMIN_NAME', 'Administrator'),
        'email' => env('BASE_TENANT_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('BASE_TENANT_ADMIN_PASSWORD', 'secret123'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | How the active account is resolved for a request, and what tenant-scoped
    | queries do when no account could be resolved.
    |
    | The resolvers run in order until one returns an account.
    |
    | on_missing_tenant:
    |   auto  - unfiltered in console and queue work, no results over HTTP
    |   allow - unfiltered everywhere (only for single-tenant installs)
    |   deny  - no results anywhere without an account in context
    |
    */

    'tenancy' => [

        'resolvers' => [
            DomainTenantResolver::class,
            ApiTokenTenantResolver::class,
            SessionTenantResolver::class,
            UserTenantResolver::class,
        ],

        'central_domains' => array_filter(
            explode(',', (string) env('BASE_TENANT_CENTRAL_DOMAINS', ''))
        ),

        'on_missing_tenant' => env('BASE_TENANT_ON_MISSING_TENANT', 'auto'),

        'propagate_to_queue' => env('BASE_TENANT_PROPAGATE_TO_QUEUE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Home URL
    |--------------------------------------------------------------------------
    |
    | The route name to redirect users to after login.
    |
    */

    'home_url' => env('BASE_TENANT_HOME_URL', 'base-tenant.dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    |
    | Configure Stripe subscription behavior including default products,
    | prices, and checkout flow URLs.
    |
    */

    'subscription' => [
        'enabled' => env('BASE_TENANT_SUBSCRIPTION_ENABLED', true),
        'default_product' => env('BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT', null),
        'default_price' => env('BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE', null),
        'success_url' => env('BASE_TENANT_SUBSCRIPTION_SUCCESS_URL', 'base-tenant.checkout.success'),
        'cancel_url' => env('BASE_TENANT_SUBSCRIPTION_CANCEL_URL', 'base-tenant.checkout.cancel'),
        'trial_days' => (int) env('BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Feature Gates
    |--------------------------------------------------------------------------
    |
    | Define the features and limits available for each subscription plan.
    | Numeric values: -1 = unlimited, 0 = disabled, >0 = limit.
    | Boolean values: true = enabled, false = disabled.
    |
    */

    'plans' => [
        'free' => [
            'name' => 'Free',
            'stripe_price_id' => null,
            'features' => [
                'max_users' => 3,
                'max_storage_gb' => 1,
                'connections' => 0,
                'webhooks' => false,
                'max_projects' => 1,
                'api_access' => false,
                'export' => false,
                'priority_support' => false,
            ],
        ],
        'starter' => [
            'name' => 'Starter',
            'stripe_price_id' => env('STRIPE_STARTER_PRICE_ID'),
            'features' => [
                'max_users' => 10,
                'max_storage_gb' => 10,
                'connections' => 3,
                'webhooks' => true,
                'max_projects' => 5,
                'api_access' => true,
                'export' => true,
                'priority_support' => false,
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'stripe_price_id' => env('STRIPE_PROFESSIONAL_PRICE_ID'),
            'features' => [
                'max_users' => -1,
                'max_storage_gb' => -1,
                'connections' => -1,
                'webhooks' => true,
                'max_projects' => -1,
                'api_access' => true,
                'export' => true,
                'priority_support' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for the notification system.
    |
    */

    'notifications' => [
        'enabled' => env('BASE_TENANT_NOTIFICATIONS_ENABLED', true),

        // Channels
        'channels' => ['database'], // Future: 'mail', 'broadcast'

        // UI Settings
        'polling_interval' => env('BASE_TENANT_NOTIFICATIONS_POLLING_INTERVAL', 30), // seconds
        'dropdown_limit' => 10, // notifications in dropdown
        'per_page' => 25, // notifications per page in full view

        // Notification Categories (enable/disable)
        'categories' => [
            'project.settings' => true,
            'project.access' => true,
            'project.milestones' => true,
            'quota.warnings' => true,
            'translation.activity' => true,
            'ai.usage' => true,
        ],

        // Priority Thresholds
        'ai_usage_threshold' => 50, // autofills per hour to trigger notification
        'quota_warning_percent' => 80, // % of quota to trigger warning
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | Configuration for the activity log / audit trail system.
    |
    */

    'activity_log' => [
        'enabled' => env('BASE_TENANT_ACTIVITY_LOG_ENABLED', true),
        'retention_days' => env('BASE_TENANT_ACTIVITY_LOG_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Catalogue
    |--------------------------------------------------------------------------
    |
    | Every granular ability the application knows about, grouped for the
    | role editor. Add your own groups here; `k2labs-base:sync-roles` writes
    | them to the database.
    |
    | Labels come from the `base-tenant::permissions` translation file, keyed
    | by the permission name.
    |
    */

    'permissions_guard' => env('BASE_TENANT_PERMISSIONS_GUARD', 'web'),

    'permissions' => [

        'users' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.impersonate',
        ],

        'roles' => [
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
        ],

        'accounts' => [
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'accounts.delete',
            'accounts.billing',
        ],

        'invitations' => [
            'invitations.view',
            'invitations.create',
            'invitations.revoke',
        ],

        'activity' => [
            'activity.view',
        ],

        'settings' => [
            'settings.view',
            'settings.update',
        ],

        'menus' => [
            'menus.view',
            'menus.update',
        ],

        'features' => [
            'features.view',
            'features.update',
        ],

        'usage' => [
            'usage.view',
        ],

        'files' => [
            'files.view',
            'files.upload',
            'files.delete',
        ],

        /*
        | The language catalogue belongs to the installation, so managing it is
        | a system administrator's job and not a customer's.
        */
        'languages' => [
            'languages.manage',
        ],

        'transfers' => [
            'transfers.view',
            'transfers.import',
            'transfers.export',
        ],

        'connections' => [
            'connections.manage',
            'webhooks.manage',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensible Roles Configuration
    |--------------------------------------------------------------------------
    |
    | Define your application's roles. System roles are predefined by the
    | package, while custom roles can be added by your application.
    |
    | Each role should have:
    | - key: Unique identifier, also the name spatie/laravel-permission uses
    | - name: Display name
    | - is_system: Whether it's a system role (true) or custom (false)
    | - permissions: Array of permission names, '*' for all, or entries with
    |   a wildcard such as 'users.*'
    |
    | Roles declared here are global: every account can assign them. Accounts
    | may also define their own roles through the role editor.
    |
    */

    'roles' => [
        'system' => [
            [
                'key' => 'administrator',
                'name' => 'Administrator',
                'is_system' => true,
                'permissions' => '*',
            ],
            [
                'key' => 'administrator-finance',
                'name' => 'Administrator Finance',
                'is_system' => true,
                'permissions' => [
                    'accounts.view',
                    'accounts.billing',
                    'users.view',
                    'activity.view',
                    'usage.view',
                ],
            ],
            [
                'key' => 'administrator-tech',
                'name' => 'Administrator Tech',
                'is_system' => true,
                'permissions' => [
                    'users.*',
                    'roles.*',
                    'accounts.view',
                    'accounts.update',
                    'activity.view',
                    'settings.*',
                    'menus.*',
                    'features.*',
                    'files.*',
                    'languages.manage',
                    'transfers.*',
                    'connections.manage',
                    'webhooks.manage',
                ],
            ],
        ],
        'customer' => [
            [
                'key' => 'customer-admin',
                'name' => 'Customer Admin',
                'is_system' => false,
                'permissions' => [
                    'users.*',
                    'roles.*',
                    'invitations.*',
                    'accounts.view',
                    'accounts.update',
                    'accounts.billing',
                    'activity.view',
                    'settings.*',
                    'menus.*',
                    'features.view',
                    'usage.view',
                    'files.*',
                    'transfers.*',
                    'connections.manage',
                    'webhooks.manage',
                ],
            ],
            [
                'key' => 'customer-user',
                'name' => 'Customer User',
                'is_system' => false,
                'permissions' => [
                    'users.view',
                    'accounts.view',
                    'settings.view',
                    'menus.view',
                    'features.view',
                    'files.view',
                    'files.upload',
                ],
            ],
            [
                'key' => 'customer-viewer',
                'name' => 'Customer Viewer',
                'is_system' => false,
                'permissions' => [
                    'accounts.view',
                    'menus.view',
                ],
            ],
            [
                'key' => 'customer-finance',
                'name' => 'Customer Finance',
                'is_system' => false,
                'permissions' => [
                    'accounts.view',
                    'accounts.billing',
                    'users.view',
                    'activity.view',
                    'usage.view',
                ],
            ],
        ],
        // Add your custom roles here
        'custom' => [
            // Example:
            // [
            //     'key' => 'custom-role',
            //     'name' => 'Custom Role',
            //     'is_system' => false,
            //     'permissions' => ['users.view'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    |
    | If you need to extend the package models in your application, you can
    | specify your custom models here. The package will use these instead
    | of the default models.
    |
    */

    'models' => [
        'user' => env('BASE_TENANT_USER_MODEL', User::class),
        'account' => env('BASE_TENANT_ACCOUNT_MODEL', Account::class),
        'role' => env('BASE_TENANT_ROLE_MODEL', Role::class),
        'permission' => env('BASE_TENANT_PERMISSION_MODEL', Permission::class),
        'user_invite' => env('BASE_TENANT_USER_INVITE_MODEL', UserInvite::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layouts
    |--------------------------------------------------------------------------
    |
    | Blade layouts the package's Livewire components render into. Point these
    | at your own layouts to keep the package pages inside your chrome.
    |
    */

    'layouts' => [
        'app' => env('BASE_TENANT_LAYOUT_APP', 'base-tenant::layouts.app'),
        'guest' => env('BASE_TENANT_LAYOUT_GUEST', 'base-tenant::layouts.guest'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Menus
    |--------------------------------------------------------------------------
    |
    | Menus are declared in code and stored in the database, where each account
    | can override ordering, labels and visibility.
    |
    */

    'menu' => [
        'enabled' => env('BASE_TENANT_MENU_ENABLED', true),

        // Cache resolved trees per account, role set and locale.
        'cache' => [
            'enabled' => env('BASE_TENANT_MENU_CACHE', true),
            'ttl' => (int) env('BASE_TENANT_MENU_CACHE_TTL', 3600),
        ],

        // Menus the package registers out of the box.
        'default_menus' => ['main', 'settings'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routing behavior for the package.
    |
    */

    'routes' => [
        'enabled' => env('BASE_TENANT_ROUTES_ENABLED', true),
        'prefix' => env('BASE_TENANT_ROUTES_PREFIX', ''),
        'middleware' => ['web'],
        'auth_middleware' => ['web', 'auth', 'verified', 'base-tenant.subscription'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the package's UI behavior and theme settings.
    |
    */

    'ui' => [
        'brand_name' => env('APP_NAME', 'Laravel'),
        'brand_logo' => env('BASE_TENANT_BRAND_LOGO', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Force Password Change
    |--------------------------------------------------------------------------
    |
    | When enabled, newly created users will be required to change their
    | password upon first login. This improves security by ensuring
    | administrators don't know the final passwords of users they create.
    |
    | - enabled: Enable/disable the feature (default: false)
    | - send_welcome_email: Send email with temporary credentials (default: true)
    |
    */

    'force_password_change' => [
        'enabled' => env('BASE_TENANT_FORCE_PASSWORD_CHANGE', false),
        'send_welcome_email' => env('BASE_TENANT_SEND_WELCOME_EMAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Configure the user invitation system. When enabled, administrators can
    | invite users to join an account via email.
    |
    */

    'invitations' => [
        'enabled' => env('BASE_TENANT_INVITATIONS_ENABLED', true),
        'expires_in_days' => env('BASE_TENANT_INVITATIONS_EXPIRES', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Generic key-value settings system for accounts and users.
    |
    */

    'settings' => [
        'enabled' => env('BASE_TENANT_SETTINGS_ENABLED', true),

        /*
        | Typed settings schemas shown in the settings editor. Classes may also
        | be registered from a service provider with Settings::register().
        */
        'schemas' => [
            // \App\Settings\BrandSettings::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Each v2 module owns the section below it and carries its own `enabled`
    | key, so the switch sits next to the settings it governs. A disabled
    | module registers nothing: no routes, no menu entries, no scheduled
    | tasks, and its facades throw rather than query tables that may not have
    | been migrated. See \Base\Tenant\Support\Module.
    |
    */

    /*
    | M1 -- Usage metering and plan limits.
    */

    'metering' => [
        'enabled' => env('BASE_TENANT_METERING_ENABLED', true),

        /*
        | Every metric this product measures. Metering refuses keys that are
        | not declared here on purpose: a typo would otherwise open a counter
        | that nothing caps, nothing shows and nobody notices.
        |
        |   type          `counter` accumulates and resets; `gauge` is a level
        |                 that goes up and down and never resets.
        |   reset         none | day | month | year. Defaults to `month` for a
        |                 counter and `none` for a gauge.
        |   feature       The plan feature that caps this metric, if any. A
        |                 metric with no feature is measured but not limited.
        |   scale         Metric units per feature unit. The feature is written
        |                 in whatever reads well on a pricing page; the metric
        |                 counts what the code has to hand.
        |   stripe_meter  Event name for Stripe Billing Meters, used by
        |                 `k2labs-base:report-usage`.
        */

        'metrics' => [

            'storage.bytes' => [
                'type' => 'gauge',
                'feature' => 'max_storage_gb',
                'scale' => 1073741824,
            ],

        ],
    ],

    /*
    | M2 -- File storage. `driver` is `vapor` for direct-to-S3 uploads signed
    | by the application, or `local` to keep everything on the host during
    | development.
    */

    'files' => [
        'enabled' => env('BASE_TENANT_FILES_ENABLED', true),
        'driver' => env('BASE_TENANT_FILES_DRIVER', 'vapor'),
        'disk' => env('BASE_TENANT_FILES_DISK', 's3'),

        /*
        | Collections for files that belong to the account rather than to one
        | of its records -- the media library. Files attached to a model take
        | their rules from that model's `fileCollections()` instead.
        |
        |   accepts   MIME patterns; `image/*` matches a family, [] takes all
        |   max_size  bytes
        |   single    replace rather than accumulate
        |   variants  renditions derived from images
        */

        'collections' => [

            'library' => [
                'accepts' => [],
                'max_size' => 100 * 1024 * 1024,
                'variants' => [
                    'thumb' => ['width' => 200, 'height' => 200, 'fit' => 'cover'],
                    'preview' => ['width' => 1200, 'fit' => 'contain'],
                ],
            ],

        ],
    ],

    /*
    | M3 -- Imports and exports.
    |
    | Every module below is built and on by default. Pre-sale is the one
    | exception, and stays off because switching it on closes standard
    | registration -- not an effect anyone should get from an upgrade.
    */

    'transfer' => [
        'enabled' => env('BASE_TENANT_TRANSFER_ENABLED', true),
        'retention_days' => env('BASE_TENANT_TRANSFER_RETENTION_DAYS', 30),

        /*
        | The handlers this product offers, keyed by the name that appears in
        | a URL. Each one extends Base\Tenant\Transfer\Import or ...\Export.
        */

        'imports' => [
            // 'guests' => \App\Transfer\GuestImport::class,
        ],

        'exports' => [
            // 'guests' => \App\Transfer\GuestExport::class,
        ],
    ],

    /*
    | M5 -- Per-account credentials for external services, and signed outbound
    | webhooks. The two are separate switches: a product can send webhooks
    | without holding third-party credentials, and the reverse.
    */

    'connections' => [
        'enabled' => env('BASE_TENANT_CONNECTIONS_ENABLED', true),
        'connectors' => [
            // 'wubook' => \App\Connectors\WubookConnector::class,
        ],
    ],

    'webhooks' => [
        'enabled' => env('BASE_TENANT_WEBHOOKS_ENABLED', true),
    ],

    /*
    | Q1 -- Languages enabled and disabled at runtime, from the database.
    */

    'languages' => [
        'enabled' => env('BASE_TENANT_LANGUAGES_ENABLED', true),

        /*
        | The locale every other one is measured against. Coverage below 100%
        | is fine -- the fallback covers the holes -- but it should be a
        | decision made with the number in front of you.
        */
        'reference' => env('BASE_TENANT_LANGUAGES_REFERENCE', 'en'),

        /*
        | LangSyncer, the optional translation service. Project level, not
        | tenant level: the translations belong to the installation. Absent
        | credentials switch the whole integration off, so a project that does
        | not use it needs no extra flag.
        |
        | The HTTP contract in LangSyncerClient is inferred from the spec and
        | has not been exercised against the real service.
        */
        'langsyncer' => [
            'url' => env('LANGSYNCER_URL', 'https://langsyncer.com'),
            'key' => env('LANGSYNCER_API_KEY'),
            'project' => env('LANGSYNCER_PROJECT'),
            'webhook_secret' => env('LANGSYNCER_WEBHOOK_SECRET'),
        ],

        /*
        | Seeded into the `languages` table on a fresh install. Everything
        | after that is data: the table is the source of truth, not this list.
        */
        'seed' => [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'enabled' => true, 'is_default' => true, 'position' => 0],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'enabled' => true, 'is_default' => false, 'position' => 1],
            ['code' => 'ca', 'name' => 'Catalan', 'native_name' => 'Català', 'enabled' => false, 'is_default' => false, 'position' => 2],
        ],
    ],

    /*
    | Q2 -- Social login. A provider appears on the login screen when its
    | credentials are present in `config/services.php`; there is no second
    | switch to keep in step with them. `allowed_domains` restricts sign-up to
    | a set of email domains, which B2B products usually want.
    */

    'social' => [
        'enabled' => env('BASE_TENANT_SOCIAL_ENABLED', true),
        'allowed_domains' => [],
    ],

    /*
    | Q3 -- Per-account correlative numbering.
    */

    'sequences' => [
        'enabled' => env('BASE_TENANT_SEQUENCES_ENABLED', true),
    ],

    /*
    | Q4 -- Onboarding checklist. Steps are declared here; the host adds its
    | own to the same list.
    */

    'onboarding' => [
        'enabled' => env('BASE_TENANT_ONBOARDING_ENABLED', true),

        /*
        | Each step is a label, somewhere to go, and a class that answers
        | whether it is done. The host adds its own to this list; a step with
        | no `completed` class is never marked done on its own, because
        | claiming otherwise would hide work that has not happened.
        */

        'steps' => [
            'complete_profile' => [
                'label' => 'base-tenant::onboarding.complete_profile',
                'description' => 'base-tenant::onboarding.complete_profile_description',
                'route' => 'base-tenant.profile',
                'completed' => ProfileCompleted::class,
            ],
            'invite_team' => [
                'label' => 'base-tenant::onboarding.invite_team',
                'description' => 'base-tenant::onboarding.invite_team_description',
                'route' => 'base-tenant.invitations.index',
                'completed' => TeamInvited::class,
            ],
        ],
    ],

    /*
    | Q5 -- Email suppression list. Bounces and complaints arrive by webhook
    | and stop every later send to that address.
    */

    'suppressions' => [
        'enabled' => env('BASE_TENANT_SUPPRESSIONS_ENABLED', true),

        'mailgun_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY'),

        /*
        | One class per mail provider, reached at
        | POST /webhooks/suppressions/{driver}.
        */

        'drivers' => [
            'mailgun' => MailgunDriver::class,
        ],
    ],

    /*
    | Q6 -- GDPR. Retention is the grace period before a soft-deleted user or
    | account is destroyed for good.
    */

    'gdpr' => [
        'enabled' => env('BASE_TENANT_GDPR_ENABLED', true),

        /*
        | Grace period before a soft-deleted record is destroyed for good.
        | Soft deletion is a grace period, not a filing system.
        */
        'retention_days' => env('BASE_TENANT_GDPR_RETENTION_DAYS', 30),

        /*
        | Bump this and every user is asked to accept again. Empty switches
        | the re-acceptance middleware off entirely.
        */
        'terms_version' => env('BASE_TENANT_TERMS_VERSION', ''),
        'terms_url' => env('BASE_TENANT_TERMS_URL'),

        /*
        | One per domain that holds personal data. A domain that is not listed
        | here is data that quietly does not appear in a legal disclosure.
        */
        'exporters' => [
            ProfileExporter::class,
            ActivityExporter::class,
        ],
    ],

    /*
    | Q7 -- Pre-sale mode. Standard registration closes and the public home
    | becomes a landing page with the founding plan and its remaining seats.
    */

    'presale' => [
        /*
        | Stays off by default even now that the module exists: switching it on
        | closes standard registration, which is not an effect anyone should
        | get by upgrading a package.
        */
        'enabled' => env('BASE_TENANT_PRESALE', false),

        'seats' => (int) env('BASE_TENANT_PRESALE_SEATS', 50),
        'price_id' => env('BASE_TENANT_PRESALE_PRICE_ID'),

        /* The plan a founding member lands on when the product opens. */
        'plan_after' => 'professional',
    ],

];
