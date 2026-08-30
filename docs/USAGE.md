# Manual

How to use everything the package gives you. The first half is the core --
tenancy, auth, permissions, billing -- and the second is the capability modules.

| | |
|---|---|
| Just installing? | [`docs/INSTALLATION.md`](INSTALLATION.md) |
| Working with an AI agent? | [`docs/agents/00-index.md`](agents/00-index.md) |
| Taking ownership of the code? | [`docs/SCAFFOLD-EJECT.md`](SCAFFOLD-EJECT.md) |
| Coming from v1? | [`docs/UPGRADE.md`](UPGRADE.md) |

**Contents**

- [Overview](#overview) · [Authentication](#authentication) · [Multi-tenancy](#multi-tenancy) · [Roles](#role-based-access-control) · [Subscriptions](#subscriptions-stripe-integration) · [Middleware](#middleware-usage) · [Preferences](#user-preferences) · [Components](#livewire-components) · [Events](#events) · [API](#api-usage-sanctum) · [Testing](#testing) · [Patterns](#common-patterns) · [Troubleshooting](#troubleshooting)
- **Modules:** [Metering](#usage-metering) · [Files](#files) · [Transfer](#imports-and-exports) · [Generator](#generating-a-module) · [Connections](#connections-and-webhooks) · [Languages](#languages) · [Social login](#social-login) · [Sequences](#sequences) · [Onboarding](#onboarding) · [Suppressions](#email-suppressions) · [GDPR](#gdpr) · [Pre-sale](#pre-sale)
- [Screens](#screens) · [Working as platform staff](#working-as-platform-staff)

---

## Overview

Base Tenant provides a complete multi-tenant SaaS foundation for Laravel 13 applications. This guide shows you how to use the package's features in your application.

## Authentication

### Login & Registration

The package provides pre-built authentication views using Livewire 3 components:

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

1. Take ownership of the views:
```bash
php artisan base-tenant:scaffold
```

2. Edit the file:
```
resources/views/tenant/livewire/auth/force-password-change.blade.php
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

## Multi-team versus single-team

The package supports two architectural modes via the `BASE_TENANT_MULTI_TEAM` configuration:

### Single-Team Mode (Default: `multi_team = false`)

**Recommended for most applications** where users belong to one account/organization.

**How it works:**
- Users have a primary `account_id` field in the `users` table
- The `account_user` pivot table is **NOT used**
- Simpler data structure and queries

**Example:**
```php
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@acme.com',
    'account_id' => $accountId,
]);

// Roles are always assigned inside an account.
Tenant::runFor($account, fn () => $user->assignRole('customer-admin'));
```

### Multi-Team Mode (`multi_team = true`)

**Use when users need to belong to multiple accounts/organizations simultaneously.**

**How it works:**
- Users still have a primary `account_id` field (default/primary account)
- The `account_user` pivot table **IS used** for additional accounts
- Users can switch between accounts, and their roles change with them

**Example:**
```php
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'account_id' => $primaryAccountId,
]);

$user->accounts()->syncWithoutDetaching([$primaryAccountId, $secondaryAccountId]);

// A different role in each account, and they never bleed into one another.
Tenant::runFor($primary, fn () => $user->assignRole('customer-admin'));
Tenant::runFor($secondary, fn () => $user->assignRole('customer-viewer'));
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
- `roles` - Role definitions, global or owned by one account
- `permissions` - The granular ability catalogue
- `account_user` - Pivot for multi-team (only used when `multi_team = true`)
- `model_has_roles` - Role assignments, scoped by `account_id` as the team key

The package automatically handles the appropriate relationships based on your configuration.

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

---

# The modules

Everything above is the core: tenancy, permissions, menus, settings, auth,
billing. What follows is the capability modules, each with its own switch in
`config/base-tenant.php` and its own set of tables.

A module that is switched off registers nothing — no routes, no menu entries,
no scheduled tasks — and its facade throws rather than querying tables the host
may never have migrated.

```php
use Base\Tenant\Support\Module;

Module::enabled(Module::FILES);   // bool
Module::active();                 // every module that is on
Module::ensure(Module::FILES);    // throws with the env var to set
```

---

## Usage metering

**Switch:** `BASE_TENANT_METERING_ENABLED` · **Doc:** `docs/agents/10-metering.md`

Counting what an account consumes, and refusing work when the plan is full.

### Declare the metric first

Metering refuses undeclared keys on purpose: a typo would otherwise open a
counter that nothing caps, nothing shows and nobody notices.

```php
// config/base-tenant.php
'metering' => [
    'metrics' => [
        'documents.generated' => [
            'type' => 'counter',        // counter accumulates and resets
            'reset' => 'month',         // none | day | month | year
            'feature' => 'max_documents',
            'stripe_meter' => 'documents',
        ],
        'storage.bytes' => [
            'type' => 'gauge',          // a level that never resets
            'feature' => 'max_storage_gb',
            'scale' => 1073741824,      // metric units per feature unit
        ],
    ],
],
```

`scale` bridges the unit the pricing page uses and the unit the code has. Without
it an account would be allowed a gigabyte for every byte it is owed.

### Use it

```php
use Base\Tenant\Facades\Meter;

Meter::increment('documents.generated');
Meter::increment('documents.generated', 5, [
    'subject' => $document,
    'metadata' => ['template' => 'invoice'],
]);
Meter::decrement('storage.bytes', $file->size);
Meter::set('storage.bytes', $recalculated);

Meter::current('documents.generated');
Meter::limit('documents.generated');      // -1 unlimited
Meter::remaining('documents.generated');
Meter::percentage('documents.generated'); // null when uncapped
Meter::wouldExceed('documents.generated', 3);
Meter::history('documents.generated', 12);
Meter::summary();

Meter::incrementOrFail('documents.generated');   // throws when full
Meter::for($account)->current('storage.bytes');
```

**`increment` versus `incrementOrFail`.** The first is a lock-free atomic add and
enforces nothing. The second takes a row lock, checks the limit and either
consumes or throws. Use `incrementOrFail` wherever the limit is meant to stop the
work: a limit enforced from an unlocked reading is not a limit, because two
requests arriving together are both told there is room for the last unit.

`UsageLimitExceededException` renders itself as **402 Payment Required** — JSON
for API clients, a page with an upgrade button for browsers.

### Gate a route

```php
Route::post('documents', GenerateDocument::class)
    ->middleware('base-tenant.metered:documents.generated,1');
```

The unit is taken *before* the work and given back if the response is 4xx/5xx or
the handler throws. In Blade: `@withinlimit('documents.generated') … @endwithinlimit`.

### Feature integration

```php
Feature::withinLimit('max_storage_gb');     // metered: usage read for you
Feature::withinLimit('max_users', $count);  // not metered: pass it
```

Calling it with neither throws rather than returning `true`: an unknown answer
must not read as permission.

---

## Files

**Switch:** `BASE_TENANT_FILES_ENABLED` · **Doc:** `docs/agents/11-files.md`

```php
use Base\Tenant\Traits\HasFiles;
use Base\Tenant\Files\FileCollection;

class Property extends Model
{
    use HasFiles;

    public function fileCollections(): array
    {
        return [
            'photos' => FileCollection::images('photos', maxSize: 10 * 1024 * 1024),
            'deeds' => FileCollection::make('deeds', accepts: ['application/pdf']),
            'cover' => FileCollection::images('cover', single: true),
        ];
    }
}
```

```php
$property->files();
$property->filesIn('photos');
$property->firstFile('cover')?->variantUrl('thumb');
$property->filesSize('photos');
```

```blade
<livewire:base-tenant.files.uploader :fileable="$property" collection="photos" />
<livewire:base-tenant.files.gallery :fileable="$property" collection="photos" />
<livewire:base-tenant.files.usage-badge />
```

### How an upload works

1. `POST /files/sign` — the browser says what it is about to send; the server
   checks the collection rules and the quota and returns a key under `tmp/`.
2. `PUT` to the returned URL — the bytes go straight to the object store. They
   never pass through PHP, which on Vapor is the only thing that works: Lambda
   caps a request body at 6 MB.
3. `POST /files/finalize` — the server inspects what actually landed: real size,
   MIME read from the bytes, quota re-checked, then the move out of `tmp/`.
4. A bucket lifecycle rule expires whatever was never finalised.

**Step three does not trust step one.** Everything the signature was based on came
from the browser.

Paths are `accounts/{account}/files/{file}/{name}` — one directory per file, so
removing it removes every derived rendition and two files with the same name
never collide.

`BASE_TENANT_FILES_DRIVER=local` makes the application accept the PUT itself, so
development and production share one code path in the browser.

---

## Imports and exports

**Switch:** `BASE_TENANT_TRANSFER_ENABLED` · **Doc:** `docs/agents/12-transfer.md`

```php
use Base\Tenant\Transfer\Import;

class GuestImport extends Import
{
    public function columns(): array
    {
        return ['name' => 'app::guests.name', 'email' => 'app::guests.email'];
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string'], 'email' => ['required', 'email']];
    }

    public function persist(array $row): void
    {
        Guest::updateOrCreate(['email' => $row['email']], $row);
    }
}
```

```php
use Base\Tenant\Facades\Transfer;

$mapping = Transfer::guessMapping($import, Csv::headers($path));
Transfer::import('guests', $uploadedFile, $mapping);
Transfer::export('guests');
```

Register both in `base-tenant.transfer.imports` / `exports`. `persist()` and
`query()` run inside the transfer's account, explicitly, so a retried job cannot
store rows against no account at all.

**Partial success is the normal outcome.** Rows that fail validation come back as
a file with their original columns plus a reason, to correct and re-upload. A
chunk that fails is retried row by row, so one bad record does not discard the
499 good ones sharing its transaction.

Semicolon delimiters and byte order marks are handled — between them they are the
usual reason a file "arrives empty". Exports are written *with* a BOM, or Excel
reads UTF-8 as Latin-1 and every accented name comes out wrong.

---

## Generating a module

**Doc:** `docs/agents/13-generator.md`

```bash
php artisan k2labs-base:make-module Booking \
    --fields="reference:string,guests:integer,starts_at:date,notes:text:nullable" \
    --files=documents \
    --pretend
```

Types: `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`,
`uuid`, `json`, each optionally `:nullable`.

Out comes the model (tenant-scoped and logged), migration, factory, policy, both
Livewire components, both views, both locales, a feature test whose first case is
tenant isolation, and an agent doc. Routes and the permission group are appended
between markers, so re-running after adding a field replaces that block instead
of appending a second copy.

`vendor:publish --tag=base-tenant-stubs` to shape what it produces; the generator
prefers the project's copy.

---

## Connections and webhooks

**Switches:** `BASE_TENANT_CONNECTIONS_ENABLED`, `BASE_TENANT_WEBHOOKS_ENABLED`
**Doc:** `docs/agents/14-connections.md`

```php
use Base\Tenant\Connections\Connector;
use Base\Tenant\Connections\HealthCheck;

class WubookConnector implements Connector
{
    public function fields(): array
    {
        return ['token' => ['label' => 'app::wubook.token', 'type' => 'password', 'required' => true]];
    }

    public function healthCheck(AccountConnection $connection): HealthCheck
    {
        return $ok ? HealthCheck::healthy() : HealthCheck::failing(__('app::wubook.rejected'));
    }

    public function client(AccountConnection $connection): mixed
    {
        return new WubookClient($connection->credentials['token']);
    }
}
```

```php
use Base\Tenant\Facades\Connection;

Connection::client('wubook');
Connection::for($account)->client('wubook', 'second-property');
Connection::store('wubook', ['token' => '...']);
Connection::forget('wubook');
```

Credentials are encrypted at rest. Health checks run nightly and have three
outcomes: `healthy`, `failing`, and `unknown` for "the provider could not be
reached" — a provider being down does not mean the customer's credentials are
wrong. The owner is notified on the transition into failing, not on every run.

```php
use Base\Tenant\Facades\Webhook;

Webhook::dispatch('booking.confirmed', ['id' => $booking->id]);
Webhook::register('https://customer.example/hooks', ['booking.*'], name: 'PMS');
```

Receivers get `X-BaseTenant-Signature` (HMAC-SHA256 over the raw body),
`X-BaseTenant-Event` and `X-BaseTenant-Delivery`. Verify against the raw body: it
is serialised once and both signed and sent, and re-encoding in between is the
classic reason a correct signature never matches.

Five attempts at 1m, 5m, 30m, 2h and 12h, held on the delivery row so a customer
can be shown when the next one is due. Twenty consecutive failures switch the
endpoint off.

---

## Languages

**Switch:** `BASE_TENANT_LANGUAGES_ENABLED` · **Doc:** `docs/agents/20-languages.md`

```php
use Base\Tenant\Facades\Language;

Language::enabled();
Language::codes();
Language::default();
Language::enable('fr');       // live, no deploy
Language::disable('fr');
Language::setDefault('es');
```

The catalogue is a table, not a config array — that is the entire point. The
default language cannot be disabled, and a user preference pointing at a
withdrawn language falls back rather than showing a half-translated interface.

```bash
php artisan k2labs-base:lang-status --missing --fail-under=90
php artisan k2labs-base:lang-push
php artisan k2labs-base:lang-pull ca --enable
```

LangSyncer is optional and off unless `LANGSYNCER_API_KEY` and
`LANGSYNCER_PROJECT` are set. Its HTTP contract is inferred from the
specification and has not been exercised against the real service; everything
around it does not depend on those details.

---

## Social login

**Switch:** `BASE_TENANT_SOCIAL_ENABLED` · **Doc:** `docs/agents/21-social-login.md`

A provider appears when its credentials are in `config/services.php` —
`google`, `linkedin-openid`, `microsoft`. No second flag to keep in step.

```blade
<x-base-tenant::social-buttons />
<livewire:base-tenant.profile.connected-accounts />
```

**The rule that matters:** an address that already has an account is never linked
automatically from the login screen. Doing so would mean anyone able to create an
identity at the provider with a known address takes the account over, and several
providers do not require proving the address. Linking starts from an
authenticated session.

Social sign-ups get no password rather than a random one, arrive verified, and
the second factor runs before the session is created. Disconnecting the last
provider from someone with no password is refused.

---

## Sequences

**Switch:** `BASE_TENANT_SEQUENCES_ENABLED` · **Doc:** `docs/agents/22-sequences.md`

```php
use Base\Tenant\Facades\Sequence;

Sequence::next('bookings');                                     // "00001"
Sequence::next('invoices', format: 'F{year}-{number:5}', period: 'year');
Sequence::peek('bookings');
Sequence::setNext('invoices', 4312);        // migrating an old system
```

Tokens: `{number}`, `{number:5}`, `{year}`, `{month}`, `{day}`. The date comes
from the period the number belongs to, not the clock, so a number taken at
23:59:59 on 31 December prints the year it was counted in.

Handed out inside a transaction holding a row lock. `max(id) + 1` looks like it
works until two requests arrive together, and a duplicated invoice number is
found by an auditor rather than by a test.

---

## Onboarding

**Switch:** `BASE_TENANT_ONBOARDING_ENABLED` · **Doc:** `docs/agents/23-onboarding.md`

```php
'onboarding' => [
    'steps' => [
        'first_property' => [
            'label' => 'app::onboarding.first_property',
            'route' => 'properties.create',
            'completed' => \App\Onboarding\HasAProperty::class,
        ],
    ],
],
```

The check is any invokable taking an `Account` and returning a boolean. A step
with no check is never marked done, because claiming otherwise hides work that
has not happened.

```blade
<livewire:base-tenant.onboarding.checklist />
```

Renders nothing once the account has finished or dismissed it. Checks are
memoised per account per request.

---

## Email suppressions

**Switch:** `BASE_TENANT_SUPPRESSIONS_ENABLED` · **Doc:** `docs/agents/24-suppressions.md`

Mostly nothing to do: a `MessageSending` listener already cancels any message to
a suppressed address, covering the package's mail, yours and anything a library
sends.

```php
use Base\Tenant\Facades\Suppression;

Suppression::isSuppressed($email);
Suppression::suppress($email, 'manual', 'ui');
Suppression::release($email);        // manual only, on purpose
```

`POST /webhooks/suppressions/mailgun`, signature checked inside a five-minute
window. Only permanent failures, complaints and unsubscribes suppress: a
temporary failure is a mailbox that was full this morning.

```bash
php artisan k2labs-base:import-suppressions bounces.csv
```

---

## GDPR

**Switch:** `BASE_TENANT_GDPR_ENABLED` · **Doc:** `docs/agents/25-gdpr.md`

Register every domain that holds personal data — one that is not registered is
data that quietly does not appear in a legal disclosure.

```php
use Base\Tenant\Gdpr\GdprExporter;

class BookingExporter implements GdprExporter
{
    public function name(): string { return 'bookings'; }

    public function export(Authenticatable $user): array
    {
        return Booking::where('guest_email', $user->email)->get()->toArray();
    }
}
```

```bash
php artisan k2labs-base:export-user-data ada@example.test
php artisan k2labs-base:purge-deleted --days=30 --dry-run
```

The export is a ZIP with one JSON per domain plus a manifest, delivered as a link
that expires. Never a password hash or a 2FA secret.

The purge destroys expired soft deletes and **anonymises** activity rather than
deleting it: an audit trail without the person is still an audit trail.

Set `BASE_TENANT_TERMS_VERSION` and anyone whose stored version differs is sent
to accept. Version, not date: proving somebody agreed is worth nothing without
knowing what they agreed to.

---

## Pre-sale

**Switch:** `BASE_TENANT_PRESALE` (**off** by default) · **Doc:** `docs/agents/26-presale.md`

```php
use Base\Tenant\Facades\Presale;

Presale::isOpen();
Presale::seatsLeft();
Presale::join($email, ['source' => 'landing']);
```

```blade
<livewire:base-tenant.presale.waitlist-form source="landing" />
<livewire:base-tenant.presale.pricing-table />
```

Switching it on closes standard registration and serves a landing page from `/`.
Seats left are counted from real customers, never a stored counter: the number on
a landing page is a promise.

```bash
php artisan k2labs-base:presale-open --batch=50
```

Each waiting person gets an account and an invitation into it, one transaction
each. The command does not edit the environment flag — that is a deploy.

The founding Stripe Checkout described in the specification is **not built**.

---

# Screens

Every management screen follows one pattern. Copy `UserManager`; it is the
reference implementation.

```php
class BookingManager extends Component
{
    use InteractsWithTable;
    use WithPagination;

    protected function sortableColumns(): array
    {
        return ['reference', 'created_at'];   // a whitelist, always
    }
}
```

`InteractsWithTable` gives search, sort, page size and density, all `#[Url]` so
the state travels in the link. It will not sort by a column you did not declare:
`sortBy` arrives from the query string, and an unfiltered column name there is an
injection point.

Page anatomy, top to bottom: breadcrumbs, header with title + count + primary
action, an optional context strip, then a panel holding toolbar, table and
footer. The toolbar is always **search | filter | view**, in that order, with the
three zones separated.

Empty, loading and error states are designed, not a centred sentence. The
skeleton row must have exactly as many cells as a real row, or the table visibly
bends as it loads.

Destructive confirmations use `flux:modal` and name the object. Never
`wire:confirm`: the native dialog is unstyled, untranslatable, and some browsers
let a user silence it for the session.

`TablePatternTest` discovers every component using the trait and requires an
entry describing how to seed it, so a new screen cannot escape the contract.

---

# Working as platform staff

A user with `is_admin` is platform staff: they pass every permission check and
belong to no account.

Because most screens edit **account** data — menus, settings, features — staff
have to put themselves into an account before they can change anything. The
account switcher offers them every account, with a search box past ten, and a way
back out to the platform view.

Entering a customer's account from platform administration is recorded in that
account's own activity log.
