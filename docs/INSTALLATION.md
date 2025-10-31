# Installation Guide

## Requirements

- PHP 8.4+
- Laravel 12.x
- MySQL/PostgreSQL
- Composer
- Node.js 18+ & NPM

## Step 1: Add Package Repository

Add this package to your `composer.json` repositories section:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../base-tenant"
        }
    ]
}
```

For private Git repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/base-tenant.git"
        }
    ]
}
```

## Step 2: Install Package

```bash
composer require base/tenant
```

## Step 3: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=base-tenant-config
```

This creates `config/base-tenant.php` where you can customize:
- Multi-team settings
- Subscription configuration
- Custom roles
- Model overrides
- Route settings

## Step 4: Configure Environment Variables

Add these to your `.env` file:

```env
# Base Tenant Configuration
BASE_TENANT_MULTI_TEAM=false
BASE_TENANT_HOME_URL=base-tenant.dashboard
BASE_TENANT_SUBSCRIPTION_ENABLED=true
BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14

# Stripe Configuration (if using subscriptions)
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT=prod_xxx
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE=price_xxx
```

## Step 5: Run Migrations

The package migrations will run automatically:

```bash
php artisan migrate
```

## Step 6: Seed Default Roles

```bash
php artisan db:seed --class="Base\Tenant\Database\Seeders\BaseTenantSeeder"
```

This creates:
- System roles (administrator, administrator-finance, administrator-tech)
- Customer roles (customer-admin, customer-user, customer-viewer, customer-finance)
- Test admin user: admin@example.com / secret123
- Test customer user: customer@example.com / secret123

## Step 7: Publish Assets (Optional)

```bash
php artisan vendor:publish --tag=base-tenant-assets
```

Assets will be published to `public/vendor/base-tenant/`.

## Step 8: Publish Views (Optional)

Only if you need to customize the views:

```bash
php artisan vendor:publish --tag=base-tenant-views
```

Views will be published to `resources/views/vendor/base-tenant/`.

## Additional Configuration

### Custom Roles

Edit `config/base-tenant.php`:

```php
'roles' => [
    'custom' => [
        [
            'key' => 'project-manager',
            'name' => 'Project Manager',
            'is_system' => false,
        ],
        [
            'key' => 'developer',
            'name' => 'Developer',
            'is_system' => false,
        ],
    ],
],
```

Then sync roles:

```bash
php artisan base-tenant:sync-roles
```

### Extending Models

Create your own User model:

```php
namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;

class User extends BaseTenantUser
{
    // Your custom methods
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
```

Update `config/base-tenant.php`:

```php
'models' => [
    'user' => \App\Models\User::class,
],
```

### Customizing Routes

Disable package routes and define your own:

```php
// config/base-tenant.php
'routes' => [
    'enabled' => false,
],
```

Then in your `routes/web.php`:

```php
use Base\Tenant\Livewire\UserManager;

Route::middleware(['auth', 'base-tenant.subscription'])->group(function () {
    Route::get('/team/users', UserManager::class)->name('team.users');
});
```

### Frontend Integration

#### Option 1: Use Package Assets

In your main layout:

```blade
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="{{ asset('vendor/base-tenant/css/app.css') }}">
</head>
<body>
    @yield('content')
    <script src="{{ asset('vendor/base-tenant/js/app.js') }}"></script>
</body>
</html>
```

#### Option 2: Integrate with Your Build

Add package resources to your `vite.config.js`:

```javascript
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
```

And import in your main CSS file:

```css
/* resources/css/app.css */
@import '../../vendor/base/tenant/resources/css/app.css';

/* Your custom styles */
```

## Verification

Test the installation:

```bash
php artisan test --filter="Base\\Tenant"
```

Visit these URLs:
- `/login` - Login page
- `/register` - Registration page
- `/dashboard` - Dashboard (after login)
- `/users` - User management (after login)

## Troubleshooting

### Migrations not running

```bash
php artisan migrate:fresh
php artisan db:seed --class="Base\Tenant\Database\Seeders\BaseTenantSeeder"
```

### Assets not loading

```bash
php artisan vendor:publish --tag=base-tenant-assets --force
php artisan optimize:clear
```

### Routes not found

Check that routes are enabled in `config/base-tenant.php`:

```php
'routes' => [
    'enabled' => true,
],
```

### Livewire components not found

```bash
php artisan livewire:discover
php artisan optimize:clear
```

## Updating

When updating the package:

```bash
composer update base/tenant
php artisan vendor:publish --tag=base-tenant-assets --force
php artisan migrate
php artisan optimize:clear
```

## Next Steps

- Read [README.md](../README.md) for feature overview
- Read [FRONTEND.md](FRONTEND.md) for frontend customization
- Check `config/base-tenant.php` for all available options
- Explore the source code in `src/` for advanced customization
