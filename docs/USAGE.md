# Usage Guide

## Overview

Base Tenant provides a complete multi-tenant SaaS foundation for Laravel 12 applications. This guide shows you how to use the package's features in your application.

## Authentication

### Login & Registration

The package provides pre-built authentication views using Livewire Volt:

- `/login` - Login page
- `/register` - Registration page
- `/forgot-password` - Password reset request
- `/reset-password/{token}` - Password reset form

### Two-Factor Authentication

Enable 2FA for a user:

```php
use Base\Tenant\Models\User;

$user = User::find($id);
$secret = app(\PragmaRX\Google2FA\Google2FA::class)->generateSecretKey();
$user->enableTwoFactorAuthentication($secret);
```

Check if user has 2FA enabled:

```php
if ($user->hasTwoFactorEnabled()) {
    // Show 2FA challenge
}
```

Disable 2FA:

```php
$user->disableTwoFactorAuthentication();
```

## Multi-Tenancy

### Creating an Account

```php
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;

$user = User::factory()->create();

// Automatically create account and assign role
$account = $user->createPrimaryAccountAndSetRole(
    accountName: "My Company",
    userRole: 'customer-admin'
);
```

### Switching Between Accounts

```php
// Get user's primary account
$primaryAccount = $user->account;

// Get all accounts user belongs to
$accounts = $user->accounts;

// Switch account
$user->account_id = $anotherAccount->id;
$user->save();
```

### Adding Users to Account

```php
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;

$account = Account::find($id);
$user = User::find($userId);

// Add user to account
$account->users()->attach($user);

// Remove user from account
$account->users()->detach($user);
```

## Role-Based Access Control

### Checking Roles

```php
// Check single role
if ($user->hasRole('customer-admin')) {
    // User is admin
}

// Check multiple roles (OR logic)
if ($user->hasAnyRole(['customer-admin', 'customer-user'])) {
    // User has at least one of these roles
}

// Authorize (throws 403 if unauthorized)
$user->authorizeRoles(['customer-admin']);
```

### Assigning Roles

```php
// Add role
$user->addRole('customer-user');

// Add role via relationship
$role = Role::where('key', 'customer-admin')->first();
$user->roles()->attach($role);
```

### Custom Roles

Define custom roles in `config/base-tenant.php`:

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

Sync roles to database:

```bash
php artisan base-tenant:sync-roles
```

## Subscriptions (Stripe Integration)

### Creating a Subscription

```php
use Illuminate\Support\Facades\Auth;

Route::get('/checkout', function () {
    return Auth::user()->account->newSubscription('default', 'price_xxx')
        ->trialDays(14)
        ->allowPromotionCodes()
        ->checkout([
            'success_url' => route('base-tenant.checkout.success'),
            'cancel_url' => route('base-tenant.checkout.cancel'),
        ]);
})->name('checkout');
```

### Checking Subscription Status

```php
$account = Auth::user()->account;

// Check if has active subscription
if ($account->hasActiveSubscription()) {
    // Allow access
}

// Check if on trial
if ($account->onTrial()) {
    // Show trial banner
}

// Check if subscribed
if ($account->subscribed('default')) {
    // User has subscription
}
```

### Managing Subscriptions

```php
// Cancel subscription
$account->subscription('default')->cancel();

// Resume subscription
$account->subscription('default')->resume();

// Swap plans
$account->subscription('default')->swap('price_new');

// Portal link
return $account->redirectToBillingPortal(route('base-tenant.dashboard'));
```

## Middleware Usage

### Require Subscription

```php
Route::middleware(['base-tenant.subscription'])->group(function () {
    Route::get('/premium-feature', function () {
        // Only accessible with active subscription
    });
});
```

### Checkout Routes (No Subscription Required)

```php
Route::middleware(['base-tenant.no-subscription'])->group(function () {
    Route::get('/checkout', function () {
        // Only accessible without subscription
    });
});
```

### Locale Middleware

```php
Route::middleware(['base-tenant.locale'])->group(function () {
    // User's locale will be applied
});
```

## User Preferences

### Localization

```php
$user = Auth::user();

// Set preferences
$user->locale = 'es';
$user->timezone = 'America/New_York';
$user->currency = 'USD';
$user->date_format = 'd/m/Y';
$user->time_format = 'H:i:s';
$user->decimal_places = 2;
$user->decimals_separator = '.';
$user->thousands_separator = ',';
$user->save();

// Apply preferences
$formatted = $user->applyTimeZone($dateTime);
$formatted = $user->applyDateFormat($date);
$formatted = $user->applyCurrencyFormat(1234.56);
```

### Getting User Initials

```php
$user = User::find($id);
echo $user->initials; // "JD" for "John Doe"
```

## Livewire Components

### User Management

```blade
{{-- Full user management interface --}}
@livewire('base-tenant.user-manager')

{{-- Create user form --}}
@livewire('base-tenant.edit-user')

{{-- Edit specific user --}}
@livewire('base-tenant.edit-user', ['user' => $user])
```

### Two-Factor Authentication

```blade
{{-- 2FA setup interface --}}
@livewire('base-tenant.two-factor-authentication')

{{-- 2FA challenge for login --}}
@livewire('base-tenant.two-factor-challenge')
```

## View Components

### App Layout

```blade
<x-base-tenant-app-layout>
    <x-slot name="header">
        <h2>Page Title</h2>
    </x-slot>

    {{-- Your content --}}
</x-base-tenant-app-layout>
```

### Guest Layout

```blade
<x-base-tenant-guest-layout>
    {{-- Login/Register forms --}}
</x-base-tenant-guest-layout>
```

## Events

The package dispatches standard Laravel events:

```php
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;

// Listen to events
Event::listen(Registered::class, function ($event) {
    // User registered
});
```

## API Usage (Sanctum)

### Creating API Tokens

```php
$user = User::find($id);
$token = $user->createToken('api-token')->plainTextToken;
```

### Protecting API Routes

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
```

## Testing

### Using Factories

```php
use Base\Tenant\Models\User;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;

// Create user
$user = User::factory()->create();

// Create admin user
$admin = User::factory()->admin()->create();

// Create user with account
$account = Account::factory()->create();
$user = User::factory()->create(['account_id' => $account->id]);

// Create role
$role = Role::create([
    'key' => 'test-role',
    'name' => 'Test Role',
    'is_system' => false,
]);
```

### Testing with Roles

```php
test('user can access admin panel', function () {
    $user = User::factory()->create();
    $role = Role::create([
        'key' => 'customer-admin',
        'name' => 'Customer Admin',
        'is_system' => false,
    ]);

    $user->roles()->attach($role);
    $user->storeRolesSession();

    expect($user->hasRole('customer-admin'))->toBeTrue();
});
```

## Best Practices

### 1. Always Check Subscriptions

```php
if (!Auth::user()->account->hasActiveSubscription()) {
    return redirect()->route('base-tenant.checkout');
}
```

### 2. Use Role Checks

```php
// In controllers
public function index()
{
    $user = Auth::user();
    $user->authorizeRoles(['customer-admin']);

    // Rest of code
}

// In views
@if(auth()->user()->hasRole('customer-admin'))
    <a href="{{ route('base-tenant.users.index') }}">Manage Users</a>
@endif
```

### 3. Cache Session Roles

```php
// After login or role changes
$user->storeRolesSession();
```

### 4. Handle Multi-Team Properly

```php
// Always check current account
$currentAccount = Auth::user()->account;

// Filter data by account
$data = Model::where('account_id', $currentAccount->id)->get();
```

### 5. Use Transactions for Account Creation

```php
DB::transaction(function () use ($user) {
    $account = $user->createPrimaryAccountAndSetRole('Company Name');
    // Additional operations
});
```

## Common Patterns

### Admin Dashboard

```php
Route::middleware(['auth', 'base-tenant.subscription'])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $user->authorizeRoles(['customer-admin']);

        $account = $user->account;
        $users = $account->users;

        return view('base-tenant::dashboard', compact('users'));
    })->name('base-tenant.dashboard');
});
```

### Account Switcher

```php
public function switchAccount(Request $request, Account $account)
{
    $user = Auth::user();

    // Check user has access to account
    if (!$user->accounts->contains($account)) {
        abort(403);
    }

    $user->account_id = $account->id;
    $user->save();

    return redirect()->route('base-tenant.dashboard');
}
```

### Invite Users

```php
use Base\Tenant\Models\UserInvite;

public function inviteUser(Request $request)
{
    $account = Auth::user()->account;

    $invite = UserInvite::create([
        'email' => $request->email,
        'account_id' => $account->id,
        'role_id' => $request->role_id,
        'token' => Str::random(32),
        'expires_at' => now()->addDays(7),
    ]);

    // Send invitation email
    Mail::to($invite->email)->send(new InvitationMail($invite));

    return redirect()->back()->with('success', 'Invitation sent!');
}
```

## Troubleshooting

### Session Role Cache Not Updating

```php
// Force refresh session roles
$user->storeRolesSession();
```

### Stripe Webhooks Not Working

```bash
# Listen to webhooks locally
stripe listen --forward-to localhost:8000/stripe/webhook
```

### User Can't Access Features

```php
// Check subscription status
dd($user->account->subscriptions);

// Check roles
dd($user->roles);

// Check session
dd(session('user.roles'));
```

For more examples, check the tests in `tests/` directory.
