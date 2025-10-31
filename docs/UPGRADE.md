# Upgrade Guide

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

Publish and modify views:

```bash
php artisan vendor:publish --tag=base-tenant-views
```

Then edit files in `resources/views/vendor/base-tenant/`.

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
php artisan base-tenant:sync-roles
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

### Asset Paths

```html
<!-- Before -->
<link rel="stylesheet" href="{{ mix('css/app.css') }}">
<script src="{{ mix('js/app.js') }}"></script>

<!-- After (using package assets) -->
<link rel="stylesheet" href="{{ asset('vendor/base-tenant/css/app.css') }}">
<script src="{{ asset('vendor/base-tenant/js/app.js') }}"></script>

<!-- Or build together with your assets -->
<link rel="stylesheet" href="{{ mix('css/app.css') }}">
```

### Tailwind Configuration

Extend package configuration:

```javascript
// tailwind.config.js
import packageConfig from './vendor/base/tenant/tailwind.config.js';

export default {
    presets: [packageConfig],
    content: [
        ...packageConfig.content,
        './resources/views/**/*.blade.php',
    ],
    // Your customizations
};
```

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

### Issue: Assets not loading

**Solution:**
```bash
php artisan vendor:publish --tag=base-tenant-assets --force
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
