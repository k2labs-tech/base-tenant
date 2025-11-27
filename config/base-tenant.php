<?php

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
    | Extensible Roles Configuration
    |--------------------------------------------------------------------------
    |
    | Define your application's roles. System roles are predefined by the
    | package, while custom roles can be added by your application.
    |
    | Each role should have:
    | - key: Unique identifier
    | - name: Display name
    | - is_system: Whether it's a system role (true) or custom (false)
    |
    */

    'roles' => [
        'system' => [
            [
                'key' => 'administrator',
                'name' => 'Administrator',
                'is_system' => true,
            ],
            [
                'key' => 'administrator-finance',
                'name' => 'Administrator Finance',
                'is_system' => true,
            ],
            [
                'key' => 'administrator-tech',
                'name' => 'Administrator Tech',
                'is_system' => true,
            ],
        ],
        'customer' => [
            [
                'key' => 'customer-admin',
                'name' => 'Customer Admin',
                'is_system' => false,
            ],
            [
                'key' => 'customer-user',
                'name' => 'Customer User',
                'is_system' => false,
            ],
            [
                'key' => 'customer-viewer',
                'name' => 'Customer Viewer',
                'is_system' => false,
            ],
            [
                'key' => 'customer-finance',
                'name' => 'Customer Finance',
                'is_system' => false,
            ],
        ],
        // Add your custom roles here
        'custom' => [
            // Example:
            // [
            //     'key' => 'custom-role',
            //     'name' => 'Custom Role',
            //     'is_system' => false,
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
        'user' => env('BASE_TENANT_USER_MODEL', \Base\Tenant\Models\User::class),
        'account' => env('BASE_TENANT_ACCOUNT_MODEL', \Base\Tenant\Models\Account::class),
        'role' => env('BASE_TENANT_ROLE_MODEL', \Base\Tenant\Models\Role::class),
        'user_invite' => env('BASE_TENANT_USER_INVITE_MODEL', \Base\Tenant\Models\UserInvite::class),
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

];
