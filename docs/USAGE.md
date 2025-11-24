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

### Force Password Change on First Login

Base Tenant includes an optional feature that forces newly created users to change their password upon first login. This improves security by ensuring administrators don't know the final passwords of users they create.

#### Enabling the Feature

Add to your `.env` file:

```env
BASE_TENANT_FORCE_PASSWORD_CHANGE=true
BASE_TENANT_SEND_WELCOME_EMAIL=true
```

Or configure in `config/base-tenant.php`:

```php
'force_password_change' => [
    'enabled' => env('BASE_TENANT_FORCE_PASSWORD_CHANGE', false),
    'send_welcome_email' => env('BASE_TENANT_SEND_WELCOME_EMAIL', true),
],
```

#### How It Works

When enabled:

1. **User Creation**: When an administrator creates a new user, the system:
   - Sets `must_change_password = true` on the user record
   - Sends a welcome email with temporary credentials (if `send_welcome_email` is true)

2. **First Login**: After login with temporary credentials:
   - Middleware detects `must_change_password = true`
   - User is redirected to `/password/change`
   - Cannot access any other route until password is changed

3. **Password Change**: User must provide:
   - Current password (the temporary one)
   - New password (must be different from current)
   - Password confirmation

4. **Completion**: After successful password change:
   - `must_change_password` flag is set to `false`
   - User is redirected to dashboard
   - Full application access is granted

#### Welcome Email

The welcome email includes:

- Greeting with user's name
- Creator's name ("John Doe has created an account for you...")
- Login credentials (email and temporary password)
- Link to login page
- Security notice about password change requirement

Example email content:

```
Hello Alice!

John Doe has created an account for you on My Application.

Here are your login credentials:
Email: alice@example.com
Temporary Password: Secret123!

[Login Now Button]

Important: For security reasons, you will be required to change
your password upon first login.

If you have any questions, please contact your administrator.
```

#### Configuration Options

**Global Configuration (Application Level):**

The feature has a global on/off switch in `.env`:

```env
BASE_TENANT_FORCE_PASSWORD_CHANGE=true   # Enable the feature globally
BASE_TENANT_SEND_WELCOME_EMAIL=true      # Send welcome emails
```

When `BASE_TENANT_FORCE_PASSWORD_CHANGE=false`, the feature is completely disabled and the Account-level setting will not appear in the UI.

**Account-Level Configuration:**

When the global setting is enabled (`BASE_TENANT_FORCE_PASSWORD_CHANGE=true`), each Account can control whether to enforce password changes for their users:

1. **Navigate to Account Settings**: Go to the Account edit page
2. **Toggle Setting**: Look for "Require password change on first login" checkbox
3. **Three States**:
   - **Checked (true)**: Always require password change for new users in this account
   - **Unchecked (false)**: Never require password change for new users in this account
   - **Null (default)**: Inherit from global setting (if global is enabled, this account will require password changes)

**Example Scenarios:**

```php
// Scenario 1: Global enabled, Account null (inherits)
// Result: Users in this account MUST change password
BASE_TENANT_FORCE_PASSWORD_CHANGE=true
$account->force_password_change = null;

// Scenario 2: Global enabled, Account explicitly disabled
// Result: Users in this account do NOT need to change password
BASE_TENANT_FORCE_PASSWORD_CHANGE=true
$account->force_password_change = false;

// Scenario 3: Global enabled, Account explicitly enabled
// Result: Users in this account MUST change password
BASE_TENANT_FORCE_PASSWORD_CHANGE=true
$account->force_password_change = true;

// Scenario 4: Global disabled
// Result: Feature disabled completely, regardless of account setting
BASE_TENANT_FORCE_PASSWORD_CHANGE=false
```

**Disable welcome email but keep password change:**

```env
BASE_TENANT_FORCE_PASSWORD_CHANGE=true
BASE_TENANT_SEND_WELCOME_EMAIL=false
```

In this mode, administrators must communicate credentials to users manually, but users still must change their password on first login (based on account setting).

#### Programmatic Usage

**Working with Users:**

Check if user must change password:

```php
if ($user->must_change_password) {
    // User hasn't changed their temporary password yet
}
```

Manually trigger password change requirement:

```php
$user->must_change_password = true;
$user->save();
```

Remove password change requirement:

```php
$user->must_change_password = false;
$user->save();
```

**Working with Accounts:**

Enable force password change for an account:

```php
$account = Account::find($accountId);
$account->force_password_change = true;
$account->save();
```

Disable force password change for an account:

```php
$account->force_password_change = false;
$account->save();
```

Reset to inherit global setting:

```php
$account->force_password_change = null;
$account->save();
```

Check if account requires password changes (considering global + account settings):

```php
$globalEnabled = config('base-tenant.force_password_change.enabled', false);
$account = Account::find($accountId);

$requiresPasswordChange = $globalEnabled &&
                         ($account->force_password_change === true ||
                          $account->force_password_change === null);

if ($requiresPasswordChange) {
    // New users in this account will need to change password
}
```

#### Route Information

The password change page is available at:

- **Route Name**: `base-tenant.password.change`
- **URL**: `/password/change`
- **Middleware**: `auth`
- **Component**: `Base\Tenant\Livewire\Auth\ForcePasswordChange`

#### Translations

The feature is fully translatable. Available translations:

**English** (`en`):
- `base-tenant::auth.change_password`
- `base-tenant::auth.change_password_required`
- `base-tenant::auth.password_change_required`
- `base-tenant::auth.account_created_by_admin`
- `base-tenant::auth.current_password`
- `base-tenant::auth.new_password`
- `base-tenant::auth.confirm_new_password`
- `base-tenant::auth.password_requirements`
- `base-tenant::auth.logout_instead`
- `base-tenant::auth.current_password_incorrect`
- `base-tenant::auth.password_changed`
- `base-tenant::auth.password_changed_success`

**Spanish** (`es`): All keys translated.

To customize, publish the language files:

```bash
php artisan vendor:publish --tag=base-tenant-lang
```

#### Security Considerations

**Best Practices:**

1. **Always enable welcome emails** - Users need their credentials
2. **Use strong temporary passwords** - The password set during user creation should be secure
3. **Communicate securely** - If not using welcome emails, use secure channels to share credentials
4. **Monitor compliance** - Check users who haven't changed their passwords

**What this protects against:**

- ✅ Administrators knowing user passwords
- ✅ Weak passwords chosen by administrators
- ✅ Shared or reused passwords
- ✅ Unauthorized access with temporary credentials

**What this doesn't protect against:**

- ❌ Users choosing weak passwords after the change
- ❌ Password reuse across services (consider additional validation)
- ❌ Compromised email accounts (where welcome emails are sent)

#### Customization

To customize the password change page:

1. Publish the views:
```bash
php artisan vendor:publish --tag=base-tenant-views
```

2. Edit the file:
```
resources/views/vendor/base-tenant/livewire/auth/force-password-change.blade.php
```

To customize the welcome email notification:

```php
// In your AppServiceProvider
use Base\Tenant\Notifications\WelcomeUserNotification;

// Extend the notification class
class CustomWelcomeNotification extends WelcomeUserNotification
{
    public function toMail($notifiable): MailMessage
    {
        // Your custom email content
    }
}

// Bind your custom class
$this->app->bind(
    WelcomeUserNotification::class,
    CustomWelcomeNotification::class
);
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
