<?php

use Base\Tenant\Connections\Webhooks\DefaultPayloadBuilder;
use Base\Tenant\Gdpr\Erasers\ActivityEraser;
use Base\Tenant\Gdpr\Erasers\FileEraser;
use Base\Tenant\Gdpr\Erasers\InvitationEraser;
use Base\Tenant\Gdpr\Erasers\MembershipEraser;
use Base\Tenant\Gdpr\Erasers\NotificationEraser;
use Base\Tenant\Gdpr\Erasers\PasswordlessEraser;
use Base\Tenant\Gdpr\Erasers\SessionEraser;
use Base\Tenant\Gdpr\Erasers\SocialAccountEraser;
use Base\Tenant\Gdpr\Erasers\TransferEraser;
use Base\Tenant\Gdpr\Exporters\ActivityExporter;
use Base\Tenant\Gdpr\Exporters\ProfileExporter;
use Base\Tenant\Gdpr\Exporters\SessionExporter;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookAttempt;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Onboarding\Checks\ProfileCompleted;
use Base\Tenant\Onboarding\Checks\TeamInvited;
use Base\Tenant\Suppressions\MailgunDriver;
use Base\Tenant\Suppressions\PostmarkDriver;
use Base\Tenant\Suppressions\ResendDriver;
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
    |   throw - MissingTenantException without an account in context, unless
    |           Tenant::runWithout() is open (then unfiltered)
    |
    | strict: write guards (immutable account_id, no cross-account update,
    | delete or restore, no account-less create), TenantBuilder (scoped
    | forceDelete, refused truncate/updateOrInsert, safe upsert, constrained
    | joins), tenant-aware pivots, and Tenant::set() throwing on unknown ids.
    |
    | restore_dispatch_context: push deferred and afterResponse() jobs under
    | the context they were built in, and give inline jobs' callers their
    | context back.
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

        'strict' => env('BASE_TENANT_STRICT_TENANCY', false),

        'join_exempt_tables' => [],

        'restore_dispatch_context' => env('BASE_TENANT_RESTORE_DISPATCH_CONTEXT', false),

        'audit' => [
            'paths' => [app_path('Models')],
            'exempt' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    |
    | Where a customer's product lives. `subdomains` hands each account a name
    | under the first central domain; `custom` lets them point a domain of
    | their own, once they have proved they control it.
    |
    | The proof is a TXT record, and it is not optional: without it, anyone who
    | can edit a DNS zone could point a hostname here and be served as the
    | account that claimed it.
    |
    */

    'domains' => [

        'enabled' => env('BASE_TENANT_DOMAINS_ENABLED', true),

        'scheme' => env('BASE_TENANT_DOMAINS_SCHEME', 'https'),

        'subdomains' => [
            'enabled' => env('BASE_TENANT_SUBDOMAINS_ENABLED', true),
            'min_length' => 3,
            'max_length' => 63,

            /*
            | Names a customer may not take. Some collide with records the
            | installation publishes, some with routes the product serves, and
            | the rest are the ones somebody picks when they want a link to
            | look like it came from you.
            */
            'reserved' => [
                'www', 'api', 'admin', 'app', 'mail', 'smtp', 'imap', 'pop',
                'ftp', 'ns', 'ns1', 'ns2', 'dns', 'mx', 'cdn', 'static',
                'assets', 'files', 'media', 'img', 'images', 'js', 'css',
                'blog', 'docs', 'help', 'support', 'status', 'billing',
                'account', 'accounts', 'login', 'signup', 'register', 'auth',
                'sso', 'oauth', 'dashboard', 'portal', 'secure', 'security',
                'test', 'dev', 'staging', 'demo', 'sandbox', 'internal',
                'root', 'system', 'webmail', 'email', 'no-reply', 'noreply',
            ],
        ],

        'custom' => [
            'enabled' => env('BASE_TENANT_CUSTOM_DOMAINS_ENABLED', true),

            /* 0 means no ceiling. */
            'max_per_account' => (int) env('BASE_TENANT_CUSTOM_DOMAINS_MAX', 3),

            /*
            | The CNAME target shown to the customer in the setup
            | instructions. Left null, the screen shows the central domain.
            */
            'target' => env('BASE_TENANT_CUSTOM_DOMAIN_TARGET'),

            'verification' => [
                'txt_prefix' => env('BASE_TENANT_DOMAIN_TXT_PREFIX', '_base-tenant-verify'),
                'recheck_after_hours' => (int) env('BASE_TENANT_DOMAIN_RECHECK_HOURS', 24),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security policies
    |--------------------------------------------------------------------------
    |
    | The rules a customer sets for their own account: a second factor for
    | everybody, which email domains may be invited, and where the account can
    | be reached from.
    |
    | The values themselves live in the typed settings store, per account. This
    | section only carries the switch and the ceilings, because a rule that
    | arrived switched on with an upgrade would lock people out of an account
    | nobody asked to change.
    |
    */

    'security' => [
        'enabled' => env('BASE_TENANT_SECURITY_ENABLED', true),

        /*
        | Active sessions and remote revocation. Kept inside this module
        | rather than given a switch of its own: it is the same concern, and
        | a customer who wants security policies wants this too.
        */
        'sessions' => [
            'enabled' => env('BASE_TENANT_SESSIONS_ENABLED', true),

            /*
            | Session rows hold an address and a device, which is personal
            | data. Keeping them past their usefulness is a liability.
            */
            'retention_days' => (int) env('BASE_TENANT_SESSIONS_RETENTION_DAYS', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passwordless sign-in
    |--------------------------------------------------------------------------
    |
    | Ways of signing in that are not a password: a link mailed to the address,
    | and a passkey held by the device.
    |
    | Neither of them skips the second factor. A magic link proves you hold the
    | mailbox, which is not the thing a second factor exists to prove.
    |
    */

    'passwordless' => [
        'enabled' => env('BASE_TENANT_PASSWORDLESS_ENABLED', true),

        'magic_links' => [
            'enabled' => env('BASE_TENANT_MAGIC_LINKS_ENABLED', true),

            /*
            | Short on purpose. A link sitting in a mailbox is a key, and the
            | window in which a leaked mailbox is also a live login should be
            | measured in minutes.
            */
            'ttl_minutes' => (int) env('BASE_TENANT_MAGIC_LINK_TTL', 15),

            /* Per address, per hour. The per-IP ceiling is four times this. */
            'max_per_hour' => (int) env('BASE_TENANT_MAGIC_LINK_MAX_PER_HOUR', 5),

            'retention_days' => (int) env('BASE_TENANT_MAGIC_LINK_RETENTION_DAYS', 7),
        ],

        'passkeys' => [
            'enabled' => env('BASE_TENANT_PASSKEYS_ENABLED', true),

            /*
            | The origin a passkey is bound to. Never a customer's own domain:
            | a credential is tied to the origin it was created on, so moving
            | the relying party per tenant would invalidate every key the
            | moment somebody changed their domain. Defaults to the host of
            | `app.url`.
            */
            'relying_party_id' => env('BASE_TENANT_PASSKEY_RP_ID'),
            'relying_party_name' => env('BASE_TENANT_PASSKEY_RP_NAME'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Home URL
    |--------------------------------------------------------------------------
    |
    | Where people land once they are in: after login, second factor, password
    | confirmation, email verification, registration without checkout,
    | invitation acceptance and account switching. A route name (default) or
    | a path such as `/app`. A route name that is not registered -- the default
    | one with `routes.app.enabled` off -- falls back to `/`.
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

        /*
        | The names belong to the customer, so an administrator of the account
        | is the person who changes them -- not platform staff.
        */
        'domains' => [
            'domains.view',
            'domains.update',
        ],

        'security' => [
            'security.view',
            'security.update',
        ],

        // `k2labs-base:make-module` writes the permissions of a generated
        // module above this line. Moving it is fine; deleting it is not — the
        // generator stops rather than guess where the block belongs.
        // base-tenant:permissions
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
    | `guest` is the layout of the login, registration, password, verification
    | and second-factor screens.
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

        /*
        | Per-group switches. Left null, a group follows `routes.enabled`, so an
        | installation that never sets them registers exactly what 3.0 did. An
        | application that only wants the package's sign-in screens sets
        | `routes.app.enabled` and `routes.subscriptions.enabled` to false.
        */

        // Login, registration, password reset, verification, second factor,
        // passkeys, magic links, social sign-in, invitation acceptance.
        'auth' => [
            'enabled' => env('BASE_TENANT_ROUTES_AUTH_ENABLED'),

            // Also answer to the names Laravel's middleware redirect to
            // (`login`, `verification.notice`, `password.confirm`, ...). Turn
            // it on when the application drops Fortify or Breeze; leave it off
            // while they still own those names.
            'laravel_names' => env('BASE_TENANT_ROUTES_LARAVEL_NAMES', false),
        ],

        // Home, dashboard, profile, settings, users and the rest of the app.
        'app' => [
            'enabled' => env('BASE_TENANT_ROUTES_APP_ENABLED'),
        ],

        // Checkout and billing portal. Also needs `subscription.enabled`.
        'subscriptions' => [
            'enabled' => env('BASE_TENANT_ROUTES_SUBSCRIPTIONS_ENABLED'),
        ],

        // Incoming webhooks (suppressions, LangSyncer).
        'webhooks' => [
            'enabled' => env('BASE_TENANT_ROUTES_WEBHOOKS_ENABLED'),
        ],
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
        | When the disk cannot sign a temporary URL, `File::url()` falls back
        | to the streaming route, which checks access on every request but
        | does not expire. Set to false when every link handed out must
        | expire: `url()` and `variantUrl()` then throw, and
        | `temporaryUrlOrNull()` returns null.
        */
        'stream_fallback' => env('BASE_TENANT_FILES_STREAM_FALLBACK', true),

        /*
        | The most pixels (width x height) an image may have for renditions
        | to be generated. Read from the header before decoding, so a small
        | file declaring a huge canvas cannot exhaust the worker's memory.
        | Over it, the original is kept without renditions and a warning is
        | logged. 0 or null removes the limit.
        */
        'max_image_pixels' => env('BASE_TENANT_FILES_MAX_IMAGE_PIXELS', 40_000_000),

        /*
        | Collections for files that belong to the account rather than to one
        | of its records -- the media library. Files attached to a model take
        | their rules from that model's `fileCollections()` instead.
        |
        |   accepts   MIME patterns; `image/*` matches a family, [] takes all.
        |             A wildcard never matches SVG, HTML, XML or scripts:
        |             name `image/svg+xml` to take SVG.
        |   max_size  bytes
        |   single    replace rather than accumulate
        |   variants  renditions derived from images
        |   public    true for files anyone may see (a status page logo):
        |             File::publicUrl() gives them a stable address with no
        |             session. Private unless it says so.
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
        /*
        | Days a transfer and the files it produced (the export, the rejected
        | rows) are kept. Applied by k2labs-base:prune-transfers. Empty keeps
        | them forever. The source file of an import is never touched.
        */
        'retention_days' => env('BASE_TENANT_TRANSFER_RETENTION_DAYS', 30),

        /*
        | Run k2labs-base:prune-transfers daily from the package's schedule.
        | Off by default, so an upgrade never starts deleting exports on its
        | own; the command can also be scheduled by the application.
        */
        'prune_schedule' => env('BASE_TENANT_TRANSFER_PRUNE_SCHEDULE', false),

        'csv' => [
            /*
            | Prefix with ' every exported cell that starts with = + - @, tab
            | or carriage return, so a spreadsheet reads it as text and not as
            | a formula (OWASP CSV injection). Plain numbers are left alone.
            */
            'escape_formulas' => env('BASE_TENANT_TRANSFER_CSV_ESCAPE_FORMULAS', true),
        ],

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

        /*
        | What a receiver sees. The defaults are the 3.0 contract; a product
        | with its own published contract changes them here.
        */
        'headers' => [
            'signature' => 'X-BaseTenant-Signature',
            'event' => 'X-BaseTenant-Event',
            'delivery' => 'X-BaseTenant-Delivery',
        ],

        /*
        | Put before the hex HMAC-SHA256 of the raw body, e.g. 'sha256='.
        */
        'signature_prefix' => env('BASE_TENANT_WEBHOOKS_SIGNATURE_PREFIX', ''),

        /*
        | Builds the body envelope. Must implement
        | Base\Tenant\Connections\Webhooks\PayloadBuilder. The default sends
        | {event, delivery, occurred_at, data}.
        */
        'payload_builder' => DefaultPayloadBuilder::class,

        /*
        | Attempts per delivery (the first included), seconds to wait after
        | each failed one -- the last gap repeats if there are more attempts
        | than entries -- and the HTTP timeout in seconds.
        */
        'attempts' => 5,
        'backoff' => [300, 1800, 7200, 43200],
        'timeout' => 15,

        /*
        | Consecutive failed deliveries before the endpoint is acted on (null:
        | never), and how: 'disable' switches it off, 'degrade' marks it with
        | degraded_at and keeps sending. Either raises
        | Base\Tenant\Events\WebhookEndpointFailing once.
        */
        'failure_limit' => 20,
        'on_failure_limit' => 'disable',

        /*
        | Seconds a degraded endpoint is left alone after its last failed
        | attempt. Deliveries due in that window are postponed, not dropped:
        | they stay pending until it ends, without spending an attempt.
        | null or 0: degraded endpoints keep receiving (3.1).
        */
        'degraded_cooldown' => null,

        /*
        | Send to endpoints registered without a secret, with no signature
        | header. Off: an endpoint with no secret is refused (3.1).
        */
        'allow_unsigned' => false,

        /*
        | Flags for the json_encode() that builds a body, e.g.
        | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE. Retries and
        | redeliveries send the stored bytes, never a re-encoding.
        */
        'json_flags' => 0,

        /*
        | Write every attempt to outbound_webhook_attempts (outcome, status,
        | error, duration). The package does not prune it: see
        | docs/agents/14-connections.md.
        */
        'log_attempts' => true,

        /*
        | Cut every URL in a stored error down to scheme://host[:port]: the
        | HTTP client puts the full request URI, credentials included, in
        | connection errors.
        */
        'redact_errors' => false,

        /*
        | Models the manager and the delivery job use. They must extend the
        | package's.
        */
        'models' => [
            'endpoint' => OutboundWebhook::class,
            'delivery' => OutboundWebhookDelivery::class,
            'attempt' => OutboundWebhookAttempt::class,
        ],
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
        | Postmark does not sign webhooks: put these credentials in the
        | webhook URL, https://user:password@host/webhooks/suppressions/postmark.
        */
        'postmark_webhook_username' => env('POSTMARK_WEBHOOK_USERNAME'),
        'postmark_webhook_password' => env('POSTMARK_WEBHOOK_PASSWORD'),

        /*
        | Resend signs through Svix; the secret starts with `whsec_`.
        */
        'resend_signing_secret' => env('RESEND_WEBHOOK_SECRET'),

        /*
        | One class per mail provider, reached at
        | POST /webhooks/suppressions/{driver}.
        */

        'drivers' => [
            'mailgun' => MailgunDriver::class,
            'postmark' => PostmarkDriver::class,
            'resend' => ResendDriver::class,
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
            SessionExporter::class,
        ],

        /*
        | The counterpart for erasure: run before a user row is destroyed for
        | good, whichever path destroys it. A domain listed above and not here
        | is data the product discloses and then fails to delete.
        */
        'erasers' => [
            ActivityEraser::class,
            SessionEraser::class,
            PasswordlessEraser::class,
            SocialAccountEraser::class,
            NotificationEraser::class,
            InvitationEraser::class,
            FileEraser::class,
            TransferEraser::class,
            MembershipEraser::class,
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
