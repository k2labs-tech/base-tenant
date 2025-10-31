# Base Tenant Package

A comprehensive multi-tenant SaaS foundation package for Laravel 12 with built-in subscription management, role-based access control, and team collaboration features.

## Features

- **Multi-Tenant Architecture**: Complete account and user management with team support
- **Subscription Management**: Stripe integration via Laravel Cashier 16
- **Extensible RBAC**: Role-based access control with customizable roles
- **Two-Factor Authentication**: Built-in 2FA support with QR codes
- **User Preferences**: Extensive localization and formatting options
- **Livewire 3 + Volt**: Modern reactive UI components
- **Flux Pro UI**: Beautiful pre-built components
- **API Support**: Laravel Sanctum authentication

## Requirements

- PHP 8.4+
- Laravel 12.x
- MySQL/PostgreSQL
- Livewire 3.5+
- Livewire Flux Pro 2.4+

## Installation

1. Add the package repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/base-tenant"
        }
    ]
}
```

2. Require the package:

```bash
composer require base/tenant
```

3. The package will auto-register via Laravel's package discovery.

4. Run migrations:

```bash
php artisan migrate
```

5. Seed default roles:

```bash
php artisan db:seed --class="Base\\Tenant\\Database\\Seeders\\InitialLoadSeeder"
```

6. Publish assets (optional):

```bash
php artisan vendor:publish --tag=base-tenant-assets
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=base-tenant-config
```

This creates `config/base-tenant.php` where you can customize:

- Multi-team support
- Subscription settings
- Custom roles
- Model overrides
- Route configuration

### Environment Variables

```env
BASE_TENANT_MULTI_TEAM=false
BASE_TENANT_HOME_URL=dashboard
BASE_TENANT_SUBSCRIPTION_ENABLED=true
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT=prod_xxx
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE=price_xxx
BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14

# Stripe Configuration
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

## Usage

### Extending Models

You can extend the package models in your application:

```php
namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;

class User extends BaseTenantUser
{
    // Add your custom methods and properties
}
```

Update `config/base-tenant.php`:

```php
'models' => [
    'user' => \App\Models\User::class,
],
```

### Adding Custom Roles

Add custom roles in `config/base-tenant.php`:

```php
'roles' => [
    'custom' => [
        [
            'key' => 'project-manager',
            'name' => 'Project Manager',
            'is_system' => false,
        ],
    ],
],
```

### Using Middleware

```php
Route::middleware(['base-tenant.subscription'])->group(function () {
    // Protected routes
});

Route::middleware(['base-tenant.no-subscription'])->group(function () {
    // Checkout routes
});

Route::middleware(['base-tenant.locale'])->group(function () {
    // Routes with user locale
});
```

### Checking Roles

```php
if ($user->hasRole('customer-admin')) {
    // User is an admin
}

if ($user->hasAnyRole(['customer-admin', 'customer-user'])) {
    // User has any of these roles
}

$user->authorizeRoles(['customer-admin']); // Throws 403 if unauthorized
```

### Livewire Components

The package registers the following Livewire components:

- `base-tenant.user-manager`: User management interface
- `base-tenant.edit-user`: User creation/editing form
- `base-tenant.two-factor-authentication`: 2FA management
- `base-tenant.logout`: Logout action
- `base-tenant.forms.login-form`: Login form

Use them in your Blade views:

```blade
@livewire('base-tenant.user-manager')
```

## Routes

The package registers the following routes:

- `/login`, `/register`, `/logout` - Authentication
- `/dashboard` - Main dashboard
- `/profile` - User profile
- `/users` - User management
- `/checkout` - Subscription checkout
- `/billing` - Stripe billing portal

## Testing

Run the package tests:

```bash
composer test
```

## License

Proprietary software. All rights reserved.
