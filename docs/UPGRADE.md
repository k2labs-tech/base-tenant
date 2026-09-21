# Upgrade Guide

## From 3.0.x to 3.0.2

Two migrations, no configuration changes:

```bash
composer update k2labs/base-tenant
php artisan migrate
```

The first widens `user_invites.token` from `varchar(50)` to `varchar(64)`, the
length invitation tokens have always been. Installations on PostgreSQL or MySQL
that patched the column themselves can drop their own migration: widening a
column that is already 64 characters does nothing, and the row their migration
left in the `migrations` table is harmless.

The second turns `usage_events.subject_id` from `bigint` into a UUID column.
Nothing is lost: on PostgreSQL and MySQL the column could never hold a subject
— the write that fills it is the one that was failing — and on SQLite the
values are copied over.

Both are safe to run on a live database: neither drops a column, an index or a
row.

## From 2.x to 3.0

3.0 is the first release on Packagist. Three things change for an existing
application: the package name, the Laravel version and the licence. The PHP
namespace (`Base\Tenant\`), config keys, commands, routes, views and
translations do not change, and there are no new mandatory migrations beyond
the ones `php artisan migrate` picks up for the new modules.

### 1. Laravel 13

3.0 requires Laravel 13 and PHP 8.4. Upgrade the application first.

### 2. Swap the package

`base/tenant` becomes `k2labs/base-tenant`:

```bash
composer remove base/tenant --no-update
composer require k2labs/base-tenant:^3.0
```

Then delete the `base/tenant` entry from the `repositories` block of
`composer.json`, if the application had one: the package now resolves from
Packagist.

### 3. Stylesheet paths

The package moves from `vendor/base/tenant/` to `vendor/k2labs/base-tenant/`.
Change the two lines the installer wrote into `resources/css/app.css`:

```css
@import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';
@source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';
```

Any other reference to `vendor/base/tenant/` in the application — Vite config,
deploy scripts, docs links — needs the same change.

If you run `php artisan k2labs-base:install` again for another reason, it
rewrites the old paths itself.

### 4. Migrate and resync

```bash
php artisan migrate
php artisan k2labs-base:sync-roles
php artisan k2labs-base:sync-menus
npm run build
```

Every new module is on by default. Turn off those the product does not want
with their `BASE_TENANT_*_ENABLED` switch before migrating, if you would rather
not have their tables.

### 5. Security middleware (optional)

The per-tenant security policies do nothing until their middleware is on the
authenticated routes — see `docs/agents/16-security.md`.

### 6. Licence

3.0 ships under a source-available licence. Read [`LICENSE.md`](../LICENSE.md):
use and modification inside your own applications is permitted, redistribution
is not.

## From 1.x to 2.0

Version 2.0 replaces session-backed roles with `spatie/laravel-permission` scoped
by `account_id`, and introduces a tenancy layer. Plan for a maintenance window:
the upgrade rewrites the `roles` table and moves role assignments.

### 1. Update dependencies

```bash
composer require spatie/laravel-permission:^6.0
composer update base/tenant
```

The package configures `config/permission.php` itself — do not publish it unless
you need to change something, and if you do, keep `teams` at `true` and
`column_names.team_foreign_key` at `account_id`.

### 2. Back up, then migrate

```bash
mysqldump your_database > backup-before-2.0.sql   # or the equivalent
php artisan migrate
php artisan k2labs-base:sync-roles --show
php artisan k2labs-base:sync-menus
```

The migrations:

- rearrange `roles`: `name` becomes the identifier (the old `key`), `display_name`
  holds the label, and `key` stays as a synced alias
- create `permissions`, `model_has_roles`, `model_has_permissions` and `role_has_permissions`
- copy every row of `role_user` into `model_has_roles`, using `account_id` as the
  team and a fixed system team for assignments that had none
- add `domain`, `subdomain` and `status` to `accounts`, and `account_id` to
  `personal_access_tokens`
- create `menus`, `menu_items` and `features`

`role_user` is left untouched so you can compare the result. Drop it once you are
satisfied.

### 3. Declare your permissions

Roles now carry permissions. Add your own to `config/base-tenant.php` and give
each role the permissions it should have, then re-run `k2labs-base:sync-roles`.
Roles with no `permissions` key are synced with none, so nothing is granted by
accident.

### 4. Replace role checks with permission checks

`hasRole()` still works, but it now answers for the account in context rather
than reading the session. Application code should generally ask about the ability
instead:

```php
// Before
if ($user->hasRole('customer-admin')) { /* ... */ }

// After
if ($user->can('invoices.approve')) { /* ... */ }
```

`storeRolesSession()` is a no-op kept for compatibility. Remove the calls.

### 5. Assign roles inside an account

Role assignment is scoped to the account in context. Outside a request — seeders,
commands, jobs — set it explicitly:

```php
use Base\Tenant\Facades\Tenant;

Tenant::runFor($account, fn () => $user->assignRole('customer-admin'));
```

`$user->roles()->attach($id, ['account_id' => $id])` no longer applies: the pivot
is `model_has_roles` and spatie fills the team column.

### 6. Scope your own models

Add `BelongsToAccount` to every model holding tenant data, then prove it:

```php
use Base\Tenant\Traits\BelongsToAccount;

class Invoice extends Model
{
    use BelongsToAccount;
}
```

```php
$this->assertTenantIsolated(Invoice::class, fn ($account) => Invoice::factory()->create());
```

Public routes that look a record up outside any tenant — an invitation accepted
from an email link, for instance — need `acrossAccounts()`.

### 7. Review the navigation

The sidebar now renders from the database. Register your entries with
`Menu::register()` in a service provider and run `k2labs-base:sync-menus`. Until
you do, only the entries the package ships with appear.

### 8. Check `on_missing_tenant`

The default, `auto`, leaves queries unfiltered in console and queue work and
returns nothing over HTTP when no account could be resolved. If your application
has admin screens that legitimately read across accounts, use `acrossAccounts()`
there rather than loosening this setting.

### Breaking changes at a glance

| Before | After |
|---|---|
| `roles.key` is the identifier | `roles.name` is the identifier, `key` is an alias |
| `roles.name` is the label | `roles.display_name` is the label |
| `role_user` pivot | `model_has_roles`, team column `account_id` |
| `hasRole()` reads the session | resolves from the database, per account |
| `$user->roles()->attach($id, [...])` | `Tenant::runFor($account, fn () => $user->assignRole($role))` |
| `session('current_account_id')` | `Tenant::current()` / `Tenant::currentId()` |
| `ActivityLog::forAccount($id)` | global scope, or `forAccount($account)` from the trait |
| `#[Layout('layouts.app')]` | `config('base-tenant.layouts.app')` |

## From Application to Package

This package was converted from a standalone Laravel 11 application to a reusable package for Laravel 12. This guide helps you understand the changes if you're migrating from the original application.

## Major Changes

### 1. Namespace Changes

**Before (Application):**
```php
use App\Models\User;
use App\Models\Account;
use App\Models\Role;
use App\Livewire\UserManager;
```

**After (Package):**
```php
use Base\Tenant\Models\User;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Livewire\UserManager;
```

### 2. Configuration File

**Before:** `config/custom.php`

**After:** `config/base-tenant.php`

Update your configuration references:

```php
// Before
config('custom.subscription.enabled')
config('custom.home-url')

// After
config('base-tenant.subscription.enabled')
config('base-tenant.home_url')
```

### 3. Route Names

All routes now have the `base-tenant.` prefix:

```php
// Before
route('dashboard')
route('users.index')
route('checkout')

// After
route('base-tenant.dashboard')
route('base-tenant.users.index')
route('base-tenant.checkout')
```

### 4. Middleware Aliases

```php
// Before
Route::middleware([HasSubscription::class])

// After
Route::middleware(['base-tenant.subscription'])
Route::middleware(['base-tenant.no-subscription'])
Route::middleware(['base-tenant.locale'])
```

### 5. Livewire Components

```blade
{{-- Before --}}
@livewire('user-manager')
@livewire('edit-user')

{{-- After --}}
@livewire('base-tenant.user-manager')
@livewire('base-tenant.edit-user')
```

### 6. View Namespaces

```php
// Before
view('dashboard')
view('profile')

// After
view('base-tenant::dashboard')
view('base-tenant::profile')
```

### 7. Translation Keys

```php
// Before
__('users.title')

// After
__('base-tenant::users.title')
```

## Laravel 12 Specific Updates

### 1. Casts Method

Models now use the `casts()` method instead of `$casts` property:

```php
// Before (Laravel 11)
protected $casts = [
    'email_verified_at' => 'datetime',
];

// After (Laravel 12)
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
    ];
}
```

### 2. Type Hints

All methods now have explicit return type hints:

```php
// Before
public function users()
{
    return $this->hasMany(User::class);
}

// After
public function users(): HasMany
{
    return $this->hasMany(User::class);
}
```

### 3. Strict Types

All PHP files now declare strict types:

```php
<?php

declare(strict_types=1);

namespace Base\Tenant\Models;
```

## Database Changes

No database schema changes are required. All migrations are backward compatible.

## Extending the Package

### If You Modified the Original Application

#### Custom User Model

Create your extended model:

```php
namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;

class User extends BaseTenantUser
{
    // Your customizations
    protected $fillable = [
        ...parent::$fillable,
        'custom_field',
    ];

    public function customMethod()
    {
        // Your code
    }
}
```

Update configuration:

```php
// config/base-tenant.php
'models' => [
    'user' => \App\Models\User::class,
],
```

#### Custom Views

Views are not publishable: the package loads them from its own namespace so
updates cannot be silently shadowed by a stale copy. To take ownership of the
markup, copy the whole package into your application:

```bash
php artisan k2labs-base:scaffold
```

The views land in `resources/views/tenant/` as yours to edit. See
`docs/SCAFFOLD-EJECT.md`.

#### Custom Routes

Disable package routes and define your own:

```php
// config/base-tenant.php
'routes' => [
    'enabled' => false,
],
```

Create your routes:

```php
// routes/web.php
use Base\Tenant\Livewire\UserManager;

Route::get('/my-users', UserManager::class)->name('my.users');
```

#### Custom Roles

Add to configuration:

```php
// config/base-tenant.php
'roles' => [
    'custom' => [
        [
            'key' => 'your-custom-role',
            'name' => 'Your Custom Role',
            'is_system' => false,
        ],
    ],
],
```

Sync to database:

```bash
php artisan k2labs-base:sync-roles
```

## Testing Updates

### Test Namespace

```php
// Before
use Tests\TestCase;

// After
use Base\Tenant\Tests\TestCase;
```

### Factory Usage

```php
// Before
User::factory()->create();

// After (still works the same)
\Base\Tenant\Models\User::factory()->create();

// Or configure in your TestCase
use Base\Tenant\Models\User;
User::factory()->create();
```

## Frontend Changes

The package no longer ships a build. There are no assets to publish and no
`tailwind.config.js` to extend — your application compiles everything with
Tailwind v4.

Replace any `asset('vendor/base-tenant/...')` link or `tailwind.config.js`
preset with an import and a `@source` in your own stylesheet:

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../vendor/base/tenant/resources/css/base-tenant.css';

@source '../../vendor/base/tenant/resources/views/**/*.blade.php';
```

Then load it with `@vite('resources/css/app.css')`. See
[FRONTEND.md](FRONTEND.md).

## Common Issues

### Issue: Routes not found

**Solution:**
```bash
php artisan route:clear
php artisan optimize:clear
```

### Issue: Views not rendering

**Solution:**
```bash
php artisan view:clear
php artisan optimize:clear
```

### Issue: Livewire components not found

**Solution:**
```bash
php artisan livewire:discover
php artisan optimize:clear
```

### Issue: Styles not applying

**Solution:** the package publishes no assets. Check that your
`resources/css/app.css` imports the theme and declares the `@source` for the
package views, then rebuild:
```bash
npm run build
```

### Issue: Database tables don't exist

**Solution:**
```bash
php artisan migrate
php artisan db:seed --class="Base\Tenant\Database\Seeders\BaseTenantSeeder"
```

## Rollback Plan

If you need to rollback to the original application:

1. Restore your original `app/` directory
2. Restore original routes in `routes/`
3. Restore original views in `resources/views/`
4. Restore original `config/custom.php`
5. Remove package from `composer.json`
6. Run `composer update`
7. Run `php artisan optimize:clear`

## Need Help?

- Check [INSTALLATION.md](INSTALLATION.md) for setup instructions
- Check [README.md](../README.md) for feature documentation
- Check [FRONTEND.md](FRONTEND.md) for asset management
- Review the source code for implementation details
