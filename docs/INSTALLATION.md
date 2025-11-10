# Installation Guide

## Quick Start (Recommended)

For the easiest installation experience, use the automated install command:

```bash
php artisan base-tenant:install
```

This command will:
- Detect and remove conflicting migrations
- Update your User model
- Publish configuration and assets
- Run migrations and seed roles
- Create test users

For manual installation or understanding the process, continue reading below.

---

## Manual Installation

## Requirements

- PHP 8.4+
- Laravel 12.x
- MySQL/PostgreSQL
- Composer
- Node.js 18+ & NPM
- **Livewire Flux Pro license** (required for UI components)

## Step 0: Configure Flux Pro Access

This package requires **Livewire Flux Pro**. You must configure Composer authentication before installation.

### Option 1: Add to composer.json (Recommended for Development)

Add Flux Pro repository and authentication to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://composer.fluxui.dev"
        }
    ],
    "config": {
        "http-basic": {
            "composer.fluxui.dev": {
                "username": "your-email@example.com",
                "password": "your-flux-license-key"
            }
        }
    }
}
```

### Option 2: Use auth.json (Recommended for Production)

Create or edit `auth.json` in your project root:

```json
{
    "http-basic": {
        "composer.fluxui.dev": {
            "username": "your-email@example.com",
            "password": "your-flux-license-key"
        }
    }
}
```

**Important:** Add `auth.json` to `.gitignore` to keep credentials private.

### Option 3: Global Configuration

For all projects on your machine:

```bash
composer config --global --auth http-basic.composer.fluxui.dev your-email@example.com your-flux-license-key
```

### Get Your Flux Pro License

1. Purchase Flux Pro at [https://fluxui.dev](https://fluxui.dev)
2. Find your license key in your account dashboard
3. Use your account email and license key for authentication

## Step 1: Add Package Repository

Add this package to your `composer.json` repositories section:

```json
{
    "repositories": {
        "base/tenant": {
            "type": "path",
            "url": "../base-tenant"
        }
    }
}
```

For private Git repository:

```json
{
    "repositories": {
        "base/tenant": {
            "type": "vcs",
            "url": "https://github.com/your-org/base-tenant.git"
        }
    }
}
```

## Step 2: Install Package

For path-based repositories (local development):

```bash
composer require base/tenant:@dev
```

Or add minimum-stability to your project's `composer.json`:

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then run:

```bash
composer require base/tenant
```

For production with version tags:

```bash
composer require base/tenant:^1.0
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

## Step 4: Configure Frontend Assets

**Critical:** Configure Tailwind to scan base-tenant views for CSS classes.

### 4.1: Import base-tenant CSS

Edit `resources/css/app.css` and add the base-tenant CSS import after the Flux import:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
@import '../../vendor/base/tenant/resources/css/app.css';  /* ADD THIS LINE */
```

### 4.2: Add base-tenant views to Tailwind scanning

In the same file, add the `@source` directive to scan base-tenant views for Tailwind classes:

```css
@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/base/tenant/resources/views/**/*.blade.php';  /* ADD THIS LINE */
```

**Complete example** of `resources/css/app.css`:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
@import '../../vendor/base/tenant/resources/css/app.css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/base/tenant/resources/views/**/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

/* Rest of your CSS... */
```

### 4.3: Define Flux Color Tokens (Tailwind CSS 4)

**Critical for Tailwind CSS 4:** Base-tenant uses Flux UI components which require custom color tokens. Add these to your `@theme` block in `resources/css/app.css`:

```css
@theme {
    /* Your existing theme variables... */

    /* Flux UI color aliases - map to standard Tailwind colors */
    --color-surface-50: var(--color-gray-50);
    --color-surface-100: var(--color-gray-100);
    --color-surface-200: var(--color-gray-200);
    --color-surface-300: var(--color-gray-300);
    --color-surface-400: var(--color-gray-400);
    --color-surface-500: var(--color-gray-500);
    --color-surface-600: var(--color-gray-600);
    --color-surface-700: var(--color-gray-700);
    --color-surface-800: var(--color-gray-800);
    --color-surface-900: var(--color-gray-900);

    --color-primary-50: var(--color-zinc-50);
    --color-primary-100: var(--color-zinc-100);
    --color-primary-200: var(--color-zinc-200);
    --color-primary-300: var(--color-zinc-300);
    --color-primary-400: var(--color-zinc-400);
    --color-primary-500: var(--color-zinc-500);
    --color-primary-600: var(--color-zinc-600);
    --color-primary-700: var(--color-zinc-700);
    --color-primary-800: var(--color-zinc-800);
    --color-primary-900: var(--color-zinc-900);

    --color-accent-50: var(--color-violet-50);
    --color-accent-100: var(--color-violet-100);
    --color-accent-200: var(--color-violet-200);
    --color-accent-300: var(--color-violet-300);
    --color-accent-400: var(--color-violet-400);
    --color-accent-500: var(--color-violet-500);
    --color-accent-600: var(--color-violet-600);
    --color-accent-700: var(--color-violet-700);
    --color-accent-800: var(--color-violet-800);
    --color-accent-900: var(--color-violet-900);

    --color-success: var(--color-green-500);
    --color-error: var(--color-red-500);
    --color-warning: var(--color-amber-500);
    --color-info: var(--color-blue-500);
}
```

**Note:** You can customize these mappings to match your brand colors. The example above uses neutral grays and violet for a clean, professional look.

Then build the assets:

```bash
npm run build
```

For development, use:

```bash
npm run dev
```

### 4.4: Configure Vite

Edit `vite.config.js` to add the Tailwind plugin and force IPv4 (required for some environments like Herd):

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '127.0.0.1', // Force IPv4 instead of IPv6
    },
});
```

**Note:** The `base-tenant:install` command handles this automatically.

## Step 5: Configure Environment Variables

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

## Step 6: Configure Fortify

**Critical:** Base-tenant provides its own authentication routes. You must disable Fortify's automatic route registration.

Edit `app/Providers/FortifyServiceProvider.php` and add `Fortify::ignoreRoutes()` in the `register()` method:

```php
public function register(): void
{
    // Disable Fortify routes - base-tenant provides its own routes
    Fortify::ignoreRoutes();
}
```

Your complete `FortifyServiceProvider` should look like:

```php
<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Disable Fortify routes - base-tenant provides its own routes
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
```

**Note:** The `base-tenant:install` command handles this automatically using a stub file.

## Step 7: Remove Conflicting Files

### 7.1: Remove Conflicting Migrations

**Important:** Remove these conflicting files from `database/migrations/` if they exist:

```bash
# Remove Laravel's default migrations that conflict with base-tenant
rm database/migrations/*create_users_table.php 2>/dev/null || true
rm database/migrations/*create_cache_table.php 2>/dev/null || true
rm database/migrations/*create_jobs_table.php 2>/dev/null || true
rm database/migrations/*two_factor*.php 2>/dev/null || true
```

These tables are provided by the base-tenant package with additional fields for multi-tenancy.

### 7.2: Remove Conflicting Layouts and Views

**Important:** If you're using a Laravel starter kit (Breeze, Jetstream, etc.), remove the layout components and views that conflict with base-tenant:

```bash
# Remove starter kit layouts (they conflict with base-tenant layouts)
rm -rf resources/views/components/layouts/app resources/views/components/layouts/app.blade.php
rm -rf resources/views/components/layouts/auth resources/views/components/layouts/auth.blade.php

# Remove starter kit dashboard (conflicts with base-tenant dashboard)
rm -f resources/views/dashboard.blade.php
```

Base-tenant provides its own layouts (`base-tenant::layouts.app` and `base-tenant::layouts.guest`) and views which will be used instead.

### 7.3: Replace Routes File

**Important:** Replace your `routes/web.php` file with the base-tenant stub to avoid route conflicts:

```bash
# Back up your existing routes if needed
cp routes/web.php routes/web.php.backup

# Copy the base-tenant stub
cp vendor/base/tenant/stubs/web.php.stub routes/web.php
```

The stub file provides a clean starting point with NO routes defined. **This is intentional** - the base-tenant package handles all core routes:
- `/` - Redirects to login (guests) or dashboard (authenticated users)
- `/dashboard` - Dashboard
- `/login`, `/register`, `/logout` - Authentication
- And more...

**Important:** Do NOT define routes for `/` in your `routes/web.php` as this will conflict with the package routes.

You can add your custom application routes to this file without conflicts.

**Note:** The `base-tenant:install` command handles this automatically. Consider using it instead of manual installation.

## Step 8: Run Migrations

The package migrations will run automatically:

```bash
php artisan migrate
```

## Step 9: Seed Default Roles

```bash
php artisan db:seed --class="Base\Tenant\Database\Seeders\BaseTenantSeeder"
```

This creates:
- System roles (administrator, administrator-finance, administrator-tech)
- Customer roles (customer-admin, customer-user, customer-viewer, customer-finance)
- Test admin user: admin@example.com / secret123
- Test customer user: customer@example.com / secret123

## Step 10: Translations (Optional)

**No action required.** The package uses namespaced translations (`base-tenant::xxx`) which work automatically without publishing.

**Optional:** Publish translation files only if you want to customize them:

```bash
php artisan vendor:publish --tag=base-tenant-lang
```

This publishes translation files to `lang/` for customization:
- English (`lang/en/`)
- Spanish (`lang/es/`)
- JSON translations (`lang/en.json`, `lang/es.json`)

**Benefits of NOT publishing:**
- ✅ Automatic updates when you update the package
- ✅ No conflicts with your app's translations
- ✅ Clean separation of concerns

**When to publish:**
- Only if you need to customize specific translations
- Laravel will check your published files first, then fall back to package translations

## Step 11: Publish Assets (Optional)

```bash
php artisan vendor:publish --tag=base-tenant-assets
```

Assets will be published to `public/vendor/base-tenant/`.

## Step 12: Publish Views (Optional)

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
