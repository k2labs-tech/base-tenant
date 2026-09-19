# Installation Guide

## Quick Start (Recommended)

```bash
composer require k2labs/base-tenant
php artisan k2labs-base:install
```

The package is published on [Packagist](https://packagist.org/packages/k2labs/base-tenant),
so no repository needs to be declared. The interactive installer:

1. **Database** - asks for the driver (sqlite, mysql, mariadb or pgsql) and only
   what that driver needs, and **tests the connection before writing anything**
2. **Multi-Team Mode** - whether users can belong to several accounts
3. **Stripe Subscriptions** - if enabled, prompts for:
   - Stripe Publishable Key (`pk_...`)
   - Stripe Secret Key (`sk_...`)
   - Default Product ID (`prod_...`)
   - Default Price ID (`price_...`)
4. **Test User** - creates a test customer account
5. **Third-party Services** (optional):
   - Flare API Key for error tracking
   - Flux UI Pro license credentials

After installation, the command:
- Configures `.env` with all provided keys
- Migrates, syncs permissions, roles and menus, and creates the administrator
  (`admin@example.com` / `secret123` unless `BASE_TENANT_ADMIN_*` say otherwise)
- Wires Flux and the package theme into `resources/css/app.css`
- Generates a setup report at `docs/SETUP.md`
- Shows next steps including Stripe CLI setup

`--no-interaction` takes the defaults for CI, and `--database` asks the database
question again on a project whose connection already works.

For manual installation or understanding the process, continue reading below.

---

## Manual Installation

## Requirements

- PHP 8.4+
- Laravel 13.x
- MySQL, MariaDB or PostgreSQL (SQLite for development and tests)
- Composer
- Node.js 18+ & NPM
- Livewire Flux UI 2.4 — the **free tier is enough**; Flux Pro is optional

## Step 1: Require the Package

```bash
composer require k2labs/base-tenant
```

To follow the development branch instead of tagged releases:

```bash
composer require k2labs/base-tenant:3.x-dev
```

The package is licensed, not open source: read [`LICENSE.md`](../LICENSE.md)
before using it.

## Step 2: Flux Pro (Optional)

The package uses only free Flux components. Flux Pro is only worth adding if you
want its components — charts, date pickers, editors — for your own screens.

Add the repository to your project's `composer.json`:

```json
{
    "repositories": {
        "livewire/flux-pro": {
            "type": "composer",
            "url": "https://composer.fluxui.dev"
        }
    }
}
```

Then store the credentials outside version control:

```bash
composer config --auth http-basic.composer.fluxui.dev your-email@example.com your-flux-license-key
composer require livewire/flux-pro
```

`composer config --auth` writes `auth.json`; make sure it is in `.gitignore`.

## Step 3: Local Development Instead of Packagist (Optional)

To work on the package itself while using it from an application, point
Composer at a local checkout instead of Packagist. See
[Development Setup](#development-setup-local-editing-with-symlinks) below.

## Step 4: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=base-tenant-config
```

This creates `config/base-tenant.php` where you can customize:
- Multi-team settings
- Subscription configuration
- Custom roles
- Model overrides
- Route settings

## Step 5: Configure Frontend Assets

**Critical:** Configure Tailwind to scan base-tenant views for CSS classes.

### 5.1: Import base-tenant CSS

Edit `resources/css/app.css` and add the base-tenant CSS import after the Flux import:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
@import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';  /* ADD THIS LINE */
```

### 5.2: Add base-tenant views to Tailwind scanning

In the same file, add the `@source` directive to scan base-tenant views for Tailwind classes:

```css
@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../base-tenant/resources/views/**/*.blade.php';         /* ADD THIS LINE */
@source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';  /* ADD THIS LINE */
```

Both paths are declared because the package may be symlinked during development
or installed under `vendor/`; the one that does not resolve is simply ignored.

**Complete example** of `resources/css/app.css`:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
@import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../base-tenant/resources/views/**/*.blade.php';
@source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';

@custom-variant dark (&:where(.dark, .dark *));

/* Rest of your CSS... */
```

### 5.3: Colour tokens

Nothing to do by hand: the `@import` added in 5.1 brings the scales the
package's views use — `accent` for primary actions and `success` / `warning` /
`danger` / `info` for states. Surfaces and text come from Tailwind's own `zinc`.

To use your own brand colours, or to read the full colour vocabulary, see
[FRONTEND.md](FRONTEND.md).

Then build the assets **in your application**:

```bash
npm run build
```

For development, use:

```bash
npm run dev
```

### 5.4: Configure Vite

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

**Note:** The `k2labs-base:install` command handles this automatically.

## Step 6: Configure Environment Variables

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

## Step 6b: Stripe CLI for Webhooks (if using subscriptions)

To receive Stripe webhook events locally (required for subscription activation after checkout), install and run the Stripe CLI:

### Install

```bash
# macOS
brew install stripe/stripe-cli/stripe

# Linux
curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
sudo apt update && sudo apt install stripe

# Windows
winget install Stripe.StripeCLI
```

### Authenticate

```bash
stripe login
```

### Forward webhooks

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

This outputs a webhook signing secret (`whsec_...`). Add it to your `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

Keep the CLI running while testing checkout flows. It forwards events like `checkout.session.completed` to Laravel Cashier, which activates the subscription automatically.

### Non-Production Bypass

In non-production environments (`local`, `testing`), if Stripe is not fully configured (missing keys, product, or price), the subscription middleware is bypassed automatically. Users can access the dashboard without a subscription. This allows development without a Stripe account.

## Step 7: Configure Fortify

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

**Note:** The `k2labs-base:install` command handles this automatically using a stub file.

## Step 8: Remove Conflicting Files

### 8.1: Remove Conflicting Migrations

**Important:** Remove these conflicting files from `database/migrations/` if they exist:

```bash
# Remove Laravel's default migrations that conflict with base-tenant
rm database/migrations/*create_users_table.php 2>/dev/null || true
rm database/migrations/*create_cache_table.php 2>/dev/null || true
rm database/migrations/*create_jobs_table.php 2>/dev/null || true
rm database/migrations/*two_factor*.php 2>/dev/null || true
```

These tables are provided by the base-tenant package with additional fields for multi-tenancy.

### 8.2: Remove Conflicting Layouts and Views

**Important:** If you're using a Laravel starter kit (Breeze, Jetstream, etc.), remove the layout components and views that conflict with base-tenant:

```bash
# Remove starter kit layouts (they conflict with base-tenant layouts)
rm -rf resources/views/components/layouts/app resources/views/components/layouts/app.blade.php
rm -rf resources/views/components/layouts/auth resources/views/components/layouts/auth.blade.php

# Remove starter kit dashboard (conflicts with base-tenant dashboard)
rm -f resources/views/dashboard.blade.php
```

Base-tenant provides its own layouts (`base-tenant::layouts.app` and `base-tenant::layouts.guest`) and views which will be used instead.

### 8.3: Replace Routes File

**Important:** Replace your `routes/web.php` file with the base-tenant stub to avoid route conflicts:

```bash
# Back up your existing routes if needed
cp routes/web.php routes/web.php.backup

# Copy the base-tenant stub
cp vendor/k2labs/base-tenant/stubs/web.php.stub routes/web.php
```

The stub file provides a clean starting point with NO routes defined. **This is intentional** - the base-tenant package handles all core routes:
- `/` - Redirects to login (guests) or dashboard (authenticated users)
- `/dashboard` - Dashboard
- `/login`, `/register`, `/logout` - Authentication
- And more...

**Important:** Do NOT define routes for `/` in your `routes/web.php` as this will conflict with the package routes.

You can add your custom application routes to this file without conflicts.

**Note:** The `k2labs-base:install` command handles this automatically. Consider using it instead of manual installation.

## Step 9: Run Migrations

The package migrations will run automatically:

```bash
php artisan migrate
```

## Step 10: Seed Default Roles

```bash
php artisan db:seed --class="Base\Tenant\Database\Seeders\BaseTenantSeeder"
```

This creates:
- System roles (administrator, administrator-finance, administrator-tech)
- Customer roles (customer-admin, customer-user, customer-viewer, customer-finance)
- Test admin user: admin@example.com / secret123
- Test customer user: customer@example.com / secret123

## Step 11: Translations (Optional)

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

## Step 12: Publish Views (Optional)

Views are not publishable on their own: a published copy drifts from the package
and updates stop reaching it. To customise the markup, copy the whole package
into your application instead:

```bash
php artisan base-tenant:scaffold
```

Views land in `resources/views/tenant/`, fully yours. See `docs/SCAFFOLD-EJECT.md`.

---

## Development Setup (Local Editing with Symlinks)

This section explains how to set up the base-tenant package for local development, allowing you to make changes to the package code and test them immediately in your application.

### Why Use Symlinks?

When you use Composer's path repository feature, Composer creates **symlinks** instead of copying files. This means:
- ✅ Changes to the package are **immediately reflected** in your app (no reinstall needed)
- ✅ You can **create branches** and propose changes via pull requests
- ✅ Perfect for fixing bugs or adding features to the package
- ✅ Test changes in a real application context before committing

### Step-by-Step Setup

#### 1. Clone the base-tenant Repository Locally

First, clone the base-tenant package repository to your local machine, **outside** your application directory:

```bash
# Navigate to your projects directory (parent of your application)
cd ~/Sites  # or wherever you keep your projects

# Clone the repository
git clone git@github.com:k2labs-tech/base-tenant.git
```

**Result:** You should now have:
```
~/Sites/
  ├── localization-hub/        # Your application
  └── base-tenant/              # The package repository
```

#### 2. Configure Composer to Use the Local Package

Edit your application's `composer.json` to use the local path instead of the remote Git repository:

```json
{
    "repositories": {
        "k2labs/base-tenant": {
            "type": "path",
            "url": "../base-tenant",
            "options": {
                "symlink": true
            }
        },
        "livewire/flux-pro": {
            "type": "composer",
            "url": "https://composer.fluxui.dev"
        }
    }
}
```

**Important Notes:**
- The `url` path is **relative** to your application directory
- If your directory structure is different, adjust the path accordingly (e.g., `../../base-tenant` if nested deeper)
- The `"symlink": true` option is **optional** - Composer uses symlinks by default for path repositories

#### 3. Install the Package with Symlink

Remove the existing package (if installed from Git) and reinstall from the local path:

```bash
# Remove the package
composer remove k2labs/base-tenant

# Clear Composer cache
composer clear-cache

# Install from local path with @dev version
composer require k2labs/base-tenant:@dev
```

**What happens:**
- Composer creates a symlink from `vendor/k2labs/base-tenant` → `../base-tenant`
- The package is now "live" - any changes you make are immediately available

**Verify the symlink:**
```bash
ls -la vendor/k2labs/base-tenant
# Should show: vendor/k2labs/base-tenant -> ../../base-tenant
```

#### 4. Making Changes to the Package

Now you can edit the package code and see changes immediately:

```bash
# Navigate to the package directory
cd ../base-tenant

# Create a new branch for your feature/fix
git checkout -b feature/add-new-feature

# Make your changes to the package files
# For example, edit src/Livewire/SomeComponent.php

# No need to run composer update - changes are live!
```

**Test your changes:**
- Refresh your application in the browser
- Changes to PHP files require a page refresh
- Changes to views (.blade.php) are usually instant
- Changes to CSS require rebuilding in your application (see below)

#### 5. Working with Package Styles

The package has no build of its own — it ships no `package.json` and compiles
nothing. Your application builds the CSS, which is why it declares the
`@source` directives from Step 5.2.

So if you edit the package's theme (`resources/css/base-tenant.css`) or add
Tailwind classes to a package view, rebuild **in your application**:

```bash
# Navigate to your application directory
cd ../localization-hub

# Rebuild, or leave `npm run dev` running
npm run build
```

Nothing is published to `public/`. See [FRONTEND.md](FRONTEND.md).

#### 6. Testing Your Changes

Before committing, ensure everything works:

```bash
# Navigate to your application directory
cd ../localization-hub

# Run package tests
php artisan test --filter="Base\\Tenant"

# Run your application tests
php artisan test

# Test in browser
php artisan serve
```

#### 7. Committing and Proposing Changes

Once your changes are ready:

```bash
# Navigate to the package directory
cd ../base-tenant

# Check your changes
git status
git diff

# Stage and commit your changes
git add .
git commit -m "feat: add new feature for X"

# Push your branch to the remote repository
git push origin feature/add-new-feature

# Create a pull request on GitHub/GitLab
# Use the URL provided in the git push output
```

**Pull Request Checklist:**
- ✅ All tests pass
- ✅ Code follows package conventions
- ✅ Documentation updated (if needed)
- ✅ CHANGELOG.md updated
- ✅ No breaking changes (or clearly documented)

#### 8. Switching Back to Production Version

When you're done developing and want to use the stable version:

```bash
# Navigate to your application directory
cd ~/Sites/localization-hub

# Edit composer.json and delete the "k2labs/base-tenant" entry from
# "repositories": without it, Composer resolves the package from Packagist.
```

```bash
# Remove the dev version
composer remove k2labs/base-tenant

# Clear cache
composer clear-cache

# Install the production version
composer require k2labs/base-tenant:^3.0
```

### Common Development Workflows

#### Fixing a Bug

```bash
# 1. Create a bug fix branch
cd ~/Sites/base-tenant
git checkout -b fix/issue-123

# 2. Make your changes
# Edit the relevant files

# 3. Test immediately in your app
cd ~/Sites/localization-hub
php artisan test

# 4. Commit and push
cd ~/Sites/base-tenant
git add .
git commit -m "fix: resolve issue with X (fixes #123)"
git push origin fix/issue-123

# 5. Create PR on GitHub
```

#### Adding a New Feature

```bash
# 1. Create a feature branch
cd ~/Sites/base-tenant
git checkout -b feature/new-component

# 2. Create the component
# Add new files, update existing ones

# 3. If you added new classes, rebuild autoloader
cd ~/Sites/localization-hub
composer dump-autoload

# 4. Test the feature
php artisan test
# Test in browser

# 5. Update documentation
cd ~/Sites/base-tenant
# Edit README.md, USAGE.md, etc.

# 6. Commit and push
git add .
git commit -m "feat: add new component for Y"
git push origin feature/new-component

# 7. Create PR
```

#### Updating Package Dependencies

```bash
# 1. Navigate to package
cd ~/Sites/base-tenant

# 2. Update composer.json
# Edit the file with new dependencies

# 3. Install dependencies
composer update

# 4. Test in your app
cd ~/Sites/localization-hub
composer update k2labs/base-tenant
php artisan test

# 5. Commit the updated composer.lock
cd ~/Sites/base-tenant
git add composer.json composer.lock
git commit -m "chore: update dependencies"
```

### Troubleshooting

#### Symlink not working

```bash
# Check if symlink exists
ls -la vendor/k2labs/base-tenant

# If not a symlink, remove and reinstall
rm -rf vendor/k2labs/base-tenant
composer clear-cache
composer require k2labs/base-tenant:@dev
```

#### Changes not appearing

```bash
# Clear all caches
php artisan optimize:clear
composer dump-autoload

# For style changes, rebuild in your application
npm run build
```

#### Autoload errors after adding new classes

```bash
# Rebuild autoloader in your application
cd ~/Sites/localization-hub
composer dump-autoload
```

#### Git conflicts when switching branches

```bash
# In the package directory
cd ~/Sites/base-tenant

# Stash your changes
git stash

# Switch branch
git checkout main

# Reapply changes (if needed)
git stash pop
```

### Best Practices

1. **Always work in branches** - Never commit directly to main/master
2. **Test thoroughly** - Use both automated tests and manual browser testing
3. **Keep commits atomic** - One logical change per commit
4. **Write clear commit messages** - Follow conventional commits format
5. **Update documentation** - Keep README.md, USAGE.md in sync with code changes
6. **Update CHANGELOG.md** - Document all user-facing changes
7. **Run code formatters** - Use `vendor/bin/pint` for Laravel Pint
8. **Create focused PRs** - One feature or fix per pull request

### Tips for Team Development

**For Package Maintainers:**
- Set up branch protection on main/master
- Require PR reviews before merging
- Run CI/CD tests on all PRs
- Tag releases with semantic versioning
- Maintain a clear CHANGELOG

**For Contributors:**
- Fork the repository first
- Clone your fork, not the main repo
- Keep your fork in sync with upstream
- Follow the project's contribution guidelines
- Be responsive to PR feedback

**Example Team Workflow:**

```bash
# 1. Fork the repository on GitHub
# 2. Clone YOUR fork
git clone https://github.com/YOUR-USERNAME/base-tenant.git

# 3. Add upstream remote
cd base-tenant
git remote add upstream https://github.com/ORIGINAL-ORG/base-tenant.git

# 4. Create feature branch
git checkout -b feature/my-feature

# 5. Make changes and commit
git add .
git commit -m "feat: add my feature"

# 6. Push to YOUR fork
git push origin feature/my-feature

# 7. Create PR from your fork to upstream
# Go to GitHub and click "Compare & pull request"
```

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

There is only one way in: your application's build. The package ships no
compiled assets and publishes nothing to `public/`, so your `app.css` imports
the theme and scans the package views — exactly what Step 5 sets up:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';

@source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';
```

Then load it from your layout with `@vite('resources/css/app.css')`. See
[FRONTEND.md](FRONTEND.md) for the colour vocabulary and how to override it.

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

### Styles not applying

The package publishes no assets — your application compiles them. Check that
`resources/css/app.css` has both the `@import` and the `@source` directives
from Step 5, then rebuild:

```bash
npm run build
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
composer update k2labs/base-tenant
php artisan migrate
php artisan optimize:clear
npm run build
```

## Next Steps

- Read [README.md](../README.md) for feature overview
- Read [FRONTEND.md](FRONTEND.md) for frontend customization
- Check `config/base-tenant.php` for all available options
- Explore the source code in `src/` for advanced customization
