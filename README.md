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

### Automatic Installation (Recommended)

1. Add the package to your project:

```bash
composer require base/tenant
```

2. Run the interactive installer:

```bash
php artisan base-tenant:install
```

The installer will:
- Ask configuration questions (multi-team, subscriptions, test user)
- Show preview of all changes
- Clean conflicting Laravel migrations
- Update User model to extend base-tenant
- Publish and configure settings
- Run migrations and seed roles
- Create test user (optional)

**Non-Interactive Mode (CI/CD):**

```bash
php artisan base-tenant:install --no-interaction
```

Uses default settings: single-team, no subscriptions, with test user.

### Manual Installation

If you prefer manual setup, see [Manual Integration Guide](docs/BASE_TENANT_INTEGRATION.md).

## Quick Start

After installation:

```bash
# Start the development server
php artisan serve

# Visit http://127.0.0.1:8000/login
# Login with: admin@test.com / password
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

## Multi-Team vs Single-Team Architecture

The package supports two architectural modes via the `BASE_TENANT_MULTI_TEAM` configuration:

### Single-Team Mode (Default: `multi_team = false`)

**Recommended for most applications** where users belong to one account/organization.

**How it works:**
- Users have a primary `account_id` field in the `users` table
- The `account_user` pivot table is **NOT used**
- Roles are scoped to the user's account via `account_id` in the `role_user` pivot table
- Simpler data structure and queries
- Better performance for single-tenant scenarios

**Example:**
```php
// User created with account_id
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@acme.com',
    'account_id' => $accountId, // Direct relationship
]);

// Roles attached with account scope
$user->roles()->attach($roleId, ['account_id' => $accountId]);
```

### Multi-Team Mode (`multi_team = true`)

**Use when users need to belong to multiple accounts/organizations simultaneously.**

**How it works:**
- Users still have a primary `account_id` field (default/primary account)
- The `account_user` pivot table **IS used** for additional accounts
- Roles are scoped per account via `account_id` in the `role_user` pivot table
- Users can switch between accounts
- More complex queries but supports multi-tenancy

**Example:**
```php
// User created with primary account_id
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'account_id' => $primaryAccountId,
]);

// Also attached to pivot for multi-team support
$user->accounts()->attach($primaryAccountId);
$user->accounts()->attach($secondaryAccountId);

// Roles per account
$user->roles()->attach($roleId, ['account_id' => $primaryAccountId]);
```

### Configuration

Set in `.env`:
```env
BASE_TENANT_MULTI_TEAM=false  # Single-team (default)
# or
BASE_TENANT_MULTI_TEAM=true   # Multi-team
```

Or in `config/base-tenant.php`:
```php
'multi_team' => env('BASE_TENANT_MULTI_TEAM', false),
```

### When to Use Each Mode

**Use Single-Team (`false`) when:**
- Users belong to one organization/company
- Simpler permission model is needed
- Better performance is required
- Example: B2B SaaS where each company has its own account

**Use Multi-Team (`true`) when:**
- Users need to belong to multiple organizations
- Freelancers/consultants work with multiple clients
- Users need to switch between different workspaces
- Example: Agency platform where users work with multiple client accounts

### Database Schema

Both modes use the same tables but with different relationships:

**Tables:**
- `users` - User records with `account_id` (primary account)
- `accounts` - Organization/team records
- `roles` - Role definitions
- `account_user` - Pivot for multi-team (only used when `multi_team = true`)
- `role_user` - Pivot with `account_id` for scoped roles (always used)

The package automatically handles the appropriate relationships based on your configuration.

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
