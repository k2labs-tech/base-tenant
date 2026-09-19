# BASE TENANT - Complete LLM Reference

> **READ THIS ENTIRE DOCUMENT BEFORE WRITING ANY CODE.**
> If you are unsure whether a feature, method, or pattern exists, search this document first.
> This prevents double development, inconsistent patterns, and architectural errors.

## Package Metadata

| Key | Value |
|-----|-------|
| Package name | `k2labs/base-tenant` |
| Namespace | `Base\Tenant\` |
| PHP | ^8.4 |
| Laravel | 12.x |
| Livewire | 3.x (NOT 4) |
| UI Library | Flux Pro 2.4+ |
| CSS | Tailwind CSS 3.4 (NOT 4) |
| Billing | Laravel Cashier 16 (Stripe) |
| Auth tokens | Laravel Sanctum 4 |
| Testing | Pest PHP 3 |

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Models - Complete API](#3-models---complete-api)
4. [Services - Complete API](#4-services---complete-api)
5. [Traits - Complete API](#5-traits---complete-api)
6. [Middleware](#6-middleware)
7. [Routes](#7-routes)
8. [Livewire Components](#8-livewire-components)
9. [Notifications](#9-notifications)
10. [Configuration Reference](#10-configuration-reference)
11. [Environment Variables](#11-environment-variables)
12. [Artisan Commands](#12-artisan-commands)
13. [Blade Directives & View Components](#13-blade-directives--view-components)
14. [Translation System](#14-translation-system)
15. [Helper Functions](#15-helper-functions)
16. [Exceptions](#16-exceptions)
17. [Conventions & Patterns](#17-conventions--patterns)
18. [DO NOT Rules](#18-do-not-rules)
19. [How to Extend](#19-how-to-extend)
20. [File Structure](#20-file-structure)
21. [Third-Party Dependencies](#21-third-party-dependencies)
22. [Session Keys](#22-session-keys)
23. [Non-Production Behavior](#23-non-production-behavior)
24. [Testing Reference](#24-testing-reference)

---

## 1. Architecture Overview

### Tenancy Model

- **Account-based tenancy**: The `Account` model is the tenant. Users belong to accounts.
- **Session-driven context**: The current tenant is stored in `session('current_account_id')`, set automatically by `SetAccountContext` middleware (auto-added to `web` group).
- **NOT database-per-tenant**: All tenants share the same database. Scoping is done via `account_id` foreign keys.

### Single-Team vs Multi-Team

Controlled by `config('base-tenant.multi_team')` / `BASE_TENANT_MULTI_TEAM` env var.

| Mode | Behavior |
|------|----------|
| Single-team (default: `false`) | Users belong to ONE account via `users.account_id`. Simpler, better performance. |
| Multi-team (`true`) | Users can belong to MANY accounts via `account_user` pivot table. Users have a primary account but can switch. |

**Code MUST handle both modes.** Always check `config('base-tenant.multi_team', false)` before deciding query strategy.

### Admin Users

A system admin is identified by: `$user->is_admin === true` OR `$user->account_id === null`. Both conditions together define a super admin. They:
- Bypass subscription checks
- Bypass account context (no `current_account_id` set)
- Can impersonate other users
- Cannot be impersonated
- See all data (not scoped by account)

### Subscription Model

- The `Account` model has the `Billable` trait (NOT User)
- Stripe subscriptions belong to Account
- Plan resolution: maps `stripe_price_id` from active subscription to `config('base-tenant.plans.*.stripe_price_id')`
- No active subscription = `free` plan

### Service Layer Pattern

- ALL business logic lives in `src/Services/`
- Controllers and Livewire components are thin -- they call services
- Services use static methods (no constructor injection)
- Never put business logic in Livewire components

### Model Extensibility

Host apps override models via `config('base-tenant.models.*')`. All relationships resolve the model class from config:
```php
$this->belongsTo(config('base-tenant.models.account', Account::class))
```

---

## 2. Database Schema

### accounts
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | uuid | no | - | PK |
| name | string | no | - | |
| email | string | yes | null | |
| phone | string | yes | null | |
| address | string | yes | null | |
| city | string | yes | null | |
| state | string | yes | null | |
| country | string | yes | null | |
| postal_code | string | yes | null | |
| vat | string | yes | null | |
| user_id | uuid | yes | null | FK->users (owner) |
| active | boolean | no | true | |
| force_password_change | boolean | yes | null | null=inherit global |
| daily_notification_summary | boolean | no | true | |
| onboarded_at | datetime | yes | null | |
| stripe_id | string | yes | null | Cashier |
| pm_type | string | yes | null | Cashier |
| pm_last_four | string | yes | null | Cashier |
| trial_ends_at | timestamp | yes | null | Cashier |
| deleted_at | timestamp | yes | null | Soft delete |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

### users
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | uuid | no | - | PK |
| name | string | no | - | |
| email | string | no | - | unique |
| email_verified_at | timestamp | yes | null | |
| password | string | no | - | hashed |
| account_id | uuid | yes | null | FK->accounts (primary) |
| is_admin | boolean | no | false | |
| locale | string | yes | null | e.g. 'en', 'es' |
| currency | string | yes | null | e.g. 'USD' |
| decimal_places | integer | yes | null | |
| decimals_separator | string | yes | null | '.' or ',' |
| thousands_separator | string | yes | null | '.' or ',' |
| timezone | string | yes | null | e.g. 'Europe/Madrid' |
| date_format | string | yes | null | e.g. 'd/m/Y' |
| time_format | string | yes | null | |
| hour_format | string | yes | null | e.g. 'H:i:s' |
| must_change_password | boolean | no | false | |
| last_account_id | uuid | yes | null | Remembers last account |
| two_factor_secret | text | yes | null | encrypted |
| two_factor_recovery_codes | text | yes | null | JSON array |
| two_factor_confirmed_at | timestamp | yes | null | |
| daily_notification_summary | boolean | yes | null | null=inherit account |
| accessed_at | datetime | yes | null | |
| remember_token | string | yes | null | |
| deleted_at | timestamp | yes | null | Soft delete |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

### roles
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | uuid | no | - | PK |
| key | string | no | - | unique |
| name | string | no | - | |
| is_system | boolean | no | false | |
| created_at | timestamp | | | |
| updated_at | timestamp | | | |

### role_user (pivot)
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| user_id | uuid | no | FK->users |
| role_id | uuid | no | FK->roles |
| account_id | uuid | yes | FK->accounts (scope) |

**Important**: `account_id` in this pivot scopes a role to a specific account. `null` = global role (for system admins).

### account_user (pivot, multi-team mode only)
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| account_id | uuid | no | FK->accounts |
| user_id | uuid | no | FK->users |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### user_invites
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | uuid | no | PK |
| email | string | no | |
| account_id | uuid | no | FK->accounts |
| role_id | uuid | yes | FK->roles |
| invited_by | uuid | no | FK->users |
| token | string | no | unique, 64 chars |
| expires_at | datetime | no | |
| accepted_at | datetime | yes | null=pending |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### activity_log
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | uuid | no | PK |
| account_id | uuid | yes | FK->accounts |
| causer_type | string | yes | Morph |
| causer_id | uuid | yes | Morph |
| subject_type | string | yes | Morph |
| subject_id | uuid | yes | Morph |
| action | string | no | 'created', 'updated', 'deleted', 'custom' |
| description | text | yes | |
| properties | json | yes | `{old: {...}, new: {...}}` |
| ip_address | string | yes | |
| user_agent | string | yes | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### settings
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | uuid | no | PK |
| settingable_type | string | no | Morph (User, Account) |
| settingable_id | uuid | no | Morph |
| group | string | no | default 'general' |
| key | string | no | |
| value | text | yes | serialized |
| type | string | no | string/integer/boolean/json/array |
| created_at | timestamp | | |
| updated_at | timestamp | | |

Unique constraint: `(settingable_type, settingable_id, key)`

### Other Tables
- **notifications**: Standard Laravel notifications table (UUID)
- **subscriptions**: Standard Laravel Cashier table
- **subscription_items**: Standard Laravel Cashier table
- **personal_access_tokens**: Standard Laravel Sanctum table
- **cache**: Standard Laravel cache table
- **jobs / job_batches / failed_jobs**: Standard Laravel queue tables
- **sessions**: Standard Laravel sessions table
- **password_reset_tokens**: Standard Laravel password resets

### Migration Files (in order)
```
0001_01_00_000000_create_accounts_table.php
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2023_08_05_104819_create_roles_table.php
2023_08_05_105633_create_role_user_table.php
2023_08_06_213047_create_account_user_table.php
2023_08_07_133152_add_user_id_to_accounts_table.php
2024_02_29_215357_create_user_invites_table.php
2024_09_03_141149_create_customer_columns.php
2024_09_03_141150_create_subscriptions_table.php
2024_09_03_141151_create_subscription_items_table.php
2024_09_05_132019_add_onboarded_at_to_accounts_table.php
2024_09_12_210327_create_personal_access_tokens_table.php
2024_11_24_000001_add_force_password_change_to_accounts_table.php
2024_11_24_000002_add_last_account_id_to_users_table.php
2025_08_03_234216_add_locale_settings_to_users_table.php
2025_08_04_122541_add_is_system_to_roles_table.php
2025_09_16_161102_add_two_factor_columns_to_users_table.php
2025_11_24_211506_add_must_change_password_to_users_table.php
2025_11_27_000001_create_notifications_table.php
2025_12_02_100000_add_daily_notification_summary_to_accounts.php
2025_12_02_100001_add_daily_notification_summary_to_users.php
2026_05_15_000001_create_activity_log_table.php
2026_05_15_000002_update_user_invites_table.php
2026_05_15_000003_create_settings_table.php
```

---

## 3. Models - Complete API

### User (`Base\Tenant\Models\User`)

**Extends**: `Illuminate\Foundation\Auth\User as Authenticatable`

**Traits**: `HasFactory`, `HasSettings`, `HasUuids`, `Impersonate`, `Notifiable`, `SoftDeletes`

**$guarded**: `['id']`

**$hidden**: `['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']`

**Casts**:
```php
'email_verified_at' => 'datetime',
'password' => 'hashed',
'accessed_at' => 'datetime',
'decimal_places' => 'integer',
'two_factor_confirmed_at' => 'datetime',
'two_factor_recovery_codes' => 'array',
```

**Relationships**:
| Method | Return Type | Notes |
|--------|-------------|-------|
| `roles()` | BelongsToMany | Uses `config('base-tenant.models.role')` |
| `account()` | BelongsTo | Primary account via `account_id` |
| `accounts()` | BelongsToMany | All accounts (with timestamps) |
| `settings()` | MorphMany | Via HasSettings trait |

**Public Methods**:
| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `createPrimaryAccountAndSetRole` | `(?string $accountName = null, string $userRole = 'customer-admin', bool $toCheckout = true)` | `Account\|Redirector` | Creates account, attaches user, sets role, saves. Sets trial period. |
| `hasAlerts` | `()` | `int` | Placeholder for alerts count |
| `currentTeam` | `()` | `Account` | Returns primary account |
| `isAdmin` | `()` | `bool` | Returns `$this->admin` |
| `addRole` | `(?string $role = null)` | `bool` | Attaches role by key. Returns false if role not found. |
| `storeRolesSession` | `()` | `void` | Caches roles in session, filtered by `current_account_id`. Gets roles where `role_user.account_id` matches OR is null. |
| `authorizeRoles` | `(array\|string $roles)` | `bool` | Aborts 401 if user doesn't have any of the roles |
| `hasAnyRole` | `(array\|string $roles)` | `bool` | True if user has at least one role |
| `hasRole` | `(string $role)` | `bool` | Checks from session cache. Auto-calls `storeRolesSession()` if session empty. |
| `initials` | `()` | `string` | Returns initials (uses accessor) |
| `applyTimeZone` | `(mixed $dateTime)` | `string` | Formats datetime with user's timezone and date_format |
| `applyDateTimeZoneFormat` | `(mixed $dateTime = null, ?string $format = null, ?string $timezone = null)` | `string` | Full datetime formatting |
| `applyDateFormat` | `(?string $date = null)` | `string` | Formats date or returns format string |
| `applyCurrencyFormat` | `(float $amount, int $decimals = 2)` | `string` | Number format with user separators |
| `enableTwoFactorAuthentication` | `(string $secret)` | `void` | Encrypts secret, generates 8 recovery codes, saves |
| `confirmTwoFactorAuthentication` | `()` | `void` | Sets confirmed_at to now() |
| `disableTwoFactorAuthentication` | `()` | `void` | Nulls all 2FA columns |
| `generateRecoveryCodes` | `()` | `array` | 8 random 10-char uppercase strings |
| `regenerateRecoveryCodes` | `()` | `void` | Generates and saves new codes |
| `hasTwoFactorEnabled` | `()` | `bool` | True if secret AND confirmed_at are set |
| `invalidateRecoveryCode` | `(string $code)` | `bool` | Removes used code from array, saves |
| `canImpersonate` | `()` | `bool` | True if `is_admin` or `account_id` is null |
| `canBeImpersonated` | `()` | `bool` | True if NOT admin and NOT null account_id |
| `determineDefaultAccount` | `()` | `string` | Priority: last_account_id > account_id > first account. Throws NoAccountException if none. |
| `shouldReceiveDailySummary` | `()` | `bool` | Cascade: user preference > account default > false |

**Accessors**:
| Accessor | Returns | Notes |
|----------|---------|-------|
| `getInitialsAttribute` | `string` | First letters of name parts |
| `getTwoFactorSecretAttribute` | `?string` | Decrypts the stored secret |

---

### Account (`Base\Tenant\Models\Account`)

**Extends**: `Illuminate\Database\Eloquent\Model`

**Traits**: `Billable`, `HasFactory`, `HasSettings`, `HasUuids`, `SoftDeletes`

**$guarded**: `['id']`

**Casts**: `force_password_change => boolean`, `onboarded_at => datetime`

**Relationships**:
| Method | Return Type | Notes |
|--------|-------------|-------|
| `users()` | BelongsToMany | Uses `config('base-tenant.models.user')` |
| `owner()` | BelongsTo | User via `user_id` column |
| `settings()` | MorphMany | Via HasSettings trait |

**Public Methods**:
| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `stripeEmail` | `()` | `?string` | Owner email or account email |
| `stripeName` | `()` | `?string` | Account name |
| `hasActiveSubscription` | `()` | `bool` | Counts active subscriptions > 0 |
| `planCan` | `(string $feature)` | `bool` | Delegates to FeatureService::accountCan |
| `planLimit` | `(string $feature)` | `int` | Delegates to FeatureService::getLimit |
| `isWithinPlanLimit` | `(string $feature, int $currentUsage)` | `bool` | Delegates to FeatureService::isWithinLimit |
| `getPlanName` | `()` | `string` | Delegates to FeatureService::getAccountPlanName |

---

### Role (`Base\Tenant\Models\Role`)

**Extends**: `Illuminate\Database\Eloquent\Model`

**Traits**: `HasExtensibleRoles`, `HasFactory`, `HasUuids`

**$guarded**: `['id']`

**Casts**: `is_system => boolean`

**Relationships**:
| Method | Return Type | Notes |
|--------|-------------|-------|
| `users()` | BelongsToMany | Uses `config('base-tenant.models.user')` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `scopeNonSystem` | `where('is_system', false)` |
| `scopeSystem` | `where('is_system', true)` |

**Static Methods (via HasExtensibleRoles trait)**: See [Traits section](#5-traits---complete-api).

---

### UserInvite (`Base\Tenant\Models\UserInvite`)

**Extends**: `Illuminate\Database\Eloquent\Model`

**Traits**: `HasFactory`, `HasUuids`

**$guarded**: `['id']`

**Casts**: `expires_at => datetime`, `accepted_at => datetime`

**Relationships**:
| Method | Return Type | Notes |
|--------|-------------|-------|
| `account()` | BelongsTo | Uses config model |
| `role()` | BelongsTo | Uses config model |
| `invitedBy()` | BelongsTo | User via `invited_by` column |

**Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isExpired()` | `bool` | `expires_at` is past |
| `isAccepted()` | `bool` | `accepted_at` is not null |
| `isPending()` | `bool` | Not accepted AND not expired |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `scopePending` | Null accepted_at AND expires_at > now |
| `scopeForAccount(string $accountId)` | Filter by account_id |

---

### ActivityLog (`Base\Tenant\Models\ActivityLog`)

**Extends**: `Illuminate\Database\Eloquent\Model`

**Traits**: `HasUuids`

**$table**: `'activity_log'`

**$guarded**: `['id']`

**Casts**: `properties => array`

**Relationships**:
| Method | Return Type |
|--------|-------------|
| `causer()` | MorphTo |
| `subject()` | MorphTo |

**Scopes**:
| Scope | Signature |
|-------|-----------|
| `scopeForAccount` | `(Builder $query, string $accountId)` |
| `scopeByAction` | `(Builder $query, string $action)` |
| `scopeForSubject` | `(Builder $query, Model $subject)` |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getOldAttribute` | `array` | `$this->properties['old'] ?? []` |
| `getNewAttribute` | `array` | `$this->properties['new'] ?? []` |
| `getChangedFieldsAttribute` | `array` | Keys of properties['new'] |

---

### Setting (`Base\Tenant\Models\Setting`)

**Extends**: `Illuminate\Database\Eloquent\Model`

**Traits**: `HasUuids`

**$guarded**: `['id']`

**Relationships**:
| Method | Return Type |
|--------|-------------|
| `settingable()` | MorphTo |

**Methods**:
| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `getCastedValueAttribute` | `()` | `mixed` | Casts value by type (boolean, integer, json/array, string) |
| `serializeValue` | `(mixed $value, string $type)` | `?string` | Static. Converts value to storable string. |

---

## 4. Services - Complete API

### FeatureService (`Base\Tenant\Services\FeatureService`)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `getPlanForAccount` | `(Account $account)` | `string` | Gets active subscription, matches stripe_price to plan config. No subscription = 'free'. |
| `getPlanConfig` | `(string $planKey)` | `?array` | Returns full plan config array |
| `accountCan` | `(Account $account, string $feature)` | `bool` | Boolean features: returns value. Numeric: -1=true, 0=false, >0=true. null=false. |
| `getLimit` | `(Account $account, string $feature)` | `int` | Returns numeric limit (0 if not set) |
| `isWithinLimit` | `(Account $account, string $feature, int $currentUsage)` | `bool` | -1=always true, else `currentUsage < limit` |
| `getAccountFeatures` | `(Account $account)` | `array` | All features for the account's plan |
| `getAccountPlanName` | `(Account $account)` | `string` | Plan display name (default: 'Free') |

---

### ActivityLogService (`Base\Tenant\Services\ActivityLogService`)

**Sensitive fields filtered**: `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `log` | `(?Model $subject = null, string $action = 'custom', ?array $oldValues = null, ?array $newValues = null, ?string $description = null)` | `ActivityLog` | Auto-reads `session('current_account_id')`, `Auth::user()` for causer, `Request::ip()`, `Request::userAgent()`. Filters sensitive fields. |
| `filterSensitive` | `(array $values)` | `array` | Removes sensitive keys from array |
| `getForAccount` | `(string $accountId, ?string $action = null, ?string $causerId = null, int $perPage = 25)` | `LengthAwarePaginator` | Paginated query with optional filters. Eager loads causer and subject. |
| `pruneOlderThan` | `(int $days = 90)` | `int` | Deletes entries older than N days. Returns count deleted. |

---

### InvitationService (`Base\Tenant\Services\InvitationService`)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `send` | `(string $email, string $accountId, string $roleId, User $inviter)` | `UserInvite` | **Auto-deletes** previous pending invites for same email+account. Creates invite with 64-char token. Sends InviteUserNotification email. |
| `resend` | `(UserInvite $invite)` | `void` | Updates expires_at. Resends email notification. |
| `accept` | `(UserInvite $invite, User $user)` | `void` | Marks accepted. Attaches account (if not already). Sets account_id if user has none. Attaches role with `account_id` in pivot. Refreshes session roles. |
| `revoke` | `(UserInvite $invite)` | `void` | Deletes the invite |
| `getPendingForAccount` | `(string $accountId)` | `Collection` | Gets pending invites with role and invitedBy relations |

---

### NotificationService (`Base\Tenant\Services\NotificationService`)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `notifyProjectUsers` | `($project, $notification, $causer)` | `void` | **Requires host app model** with `getAllMembers()` method. Excludes causer. |
| `notifySpecificUsers` | `(array $users, $notification)` | `void` | Sends to exact user list |
| `getUnreadCount` | `($user)` | `int` | Count of unread notifications |
| `markAsRead` | `($user, string $notificationId)` | `void` | Marks single notification read |
| `markAllAsRead` | `($user)` | `void` | Marks all unread as read |

**Note**: `notifyProjectUsers` is designed for host apps that have a Project model with `getAllMembers()`. It will NOT work without this method on the passed `$project` object.

---

### SettingService (`Base\Tenant\Services\SettingService`)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `get` | `(string $key, mixed $default = null, ?User $user = null, ?Account $account = null)` | `mixed` | Cascade: user setting > account setting > default |
| `resolve` | `(string $key, mixed $default = null)` | `mixed` | Auto-reads `auth()->user()` and `session('current_account_id')`. Calls `get()` with resolved models. |

---

## 5. Traits - Complete API

### LogsActivity (`Base\Tenant\Traits\LogsActivity`)

**Boot behavior**: Registers model observers for `created`, `updated`, `deleted` events. Respects `config('base-tenant.activity_log.enabled')` -- does nothing if disabled.

- On `created`: logs with `$model->getAttributes()` as new values
- On `updated`: logs with dirty attributes. Skips if nothing dirty.
- On `deleted`: logs action only (no values)

| Method | Returns | Description |
|--------|---------|-------------|
| `activities()` | `MorphMany` | Relationship to ActivityLog as subject |

---

### HasSettings (`Base\Tenant\Traits\HasSettings`)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `settings()` | `()` | `MorphMany` | Relationship to Setting model |
| `getSetting` | `(string $key, mixed $default = null)` | `mixed` | Returns casted value or default |
| `setSetting` | `(string $key, mixed $value, string $type = 'string', string $group = 'general')` | `Setting` | Uses `updateOrCreate` by key |
| `removeSetting` | `(string $key)` | `bool` | Deletes setting by key |
| `getSettingsByGroup` | `(string $group)` | `array` | Returns `[key => castedValue]` for group |
| `getAllSettings` | `()` | `array` | Returns all `[key => castedValue]` |
| `setManySettings` | `(array $settings, string $group = 'general')` | `void` | Bulk set. Array values can be `value` or `['value' => x, 'type' => y]` |

---

### HasExtensibleRoles (`Base\Tenant\Traits\HasExtensibleRoles`)

All methods are `public static`.

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `getAllConfiguredRoles` | `()` | `Collection` | Merges system + customer + custom roles from config |
| `getSystemRoles` | `()` | `Collection` | `config('base-tenant.roles.system')` |
| `getCustomerRoles` | `()` | `Collection` | `config('base-tenant.roles.customer')` |
| `getCustomRoles` | `()` | `Collection` | `config('base-tenant.roles.custom')` |
| `syncRolesToDatabase` | `()` | `void` | Uses `updateOrCreate` by `key`. Sets name and is_system. |
| `roleExists` | `(string $key)` | `bool` | Checks if key exists in config |
| `getRoleByKey` | `(string $key)` | `?array` | Returns role config array or null |

---

## 6. Middleware

### Registration

All registered in `BaseTenantServiceProvider::registerMiddleware()`:

| Alias | Class | Auto-added to web group? |
|-------|-------|--------------------------|
| `base-tenant.subscription` | `HasSubscription` | No |
| `base-tenant.no-subscription` | `DoesNotHaveSubscription` | No |
| `base-tenant.locale` | `SetLocale` | No |
| `base-tenant.password-changed` | `EnsurePasswordChanged` | **Yes, if** `force_password_change.enabled = true` |
| `base-tenant.account-context` | `SetAccountContext` | **Always yes** |
| `base-tenant.feature` | `HasFeature` | No (parametric) |

### SetAccountContext

- Auto-added to `web` middleware group
- Skips guests (no auth)
- Skips super admins (`is_admin` or `account_id` is null)
- Sets `session('current_account_id')` using `$user->determineDefaultAccount()` only if not already set

### HasSubscription

**Bypass conditions** (allows request through without subscription):
1. `config('base-tenant.subscription.enabled')` is false
2. User `is_admin` is true
3. NOT production environment AND Stripe is not configured (missing any of: `cashier.key`, `cashier.secret`, `subscription.default_product`, `subscription.default_price`)

If none bypass, checks `$user->account->hasActiveSubscription()`. Redirects to `base-tenant.checkout` if false.

### DoesNotHaveSubscription

Inverse logic. Redirects TO dashboard if:
1. User is admin
2. Non-production AND Stripe not configured
3. User HAS active subscription

Used only on checkout route.

### EnsurePasswordChanged

- Only active if `config('base-tenant.force_password_change.enabled')` is true
- Checks `$user->must_change_password`
- Allows: `base-tenant.password.change` route, `logout` route, Livewire requests (`livewire/*` or `X-Livewire` header)
- Redirects to `base-tenant.password.change` otherwise

### SetLocale

- If authenticated and user has `locale` preference: sets app locale and session
- Falls back to session locale if exists

### HasFeature (parametric)

Usage: `middleware('base-tenant.feature:api_access')`

- Gets `current_account_id` from session (aborts 403 if missing)
- Checks `FeatureService::accountCan($account, $feature)`
- On failure: JSON requests get 403, web requests redirect to `base-tenant.upgrade`

---

## 7. Routes

All routes are conditionally loaded based on `config('base-tenant.routes.enabled')`.
Prefix: `config('base-tenant.routes.prefix', '')`.

### Auth Routes (routes/auth.php)

| Method | URI | Name | Component | Middleware |
|--------|-----|------|-----------|------------|
| GET | `/register` | `base-tenant.register` | `Auth\Register` | web, guest |
| GET | `/login` | `base-tenant.login` | `Auth\Login` | web, guest |
| GET | `/forgot-password` | `base-tenant.password.request` | `Auth\ForgotPassword` | web, guest |
| GET | `/reset-password/{token}` | `base-tenant.password.reset` | `Auth\ResetPassword` | web, guest |
| GET | `/two-factor-challenge` | `base-tenant.two-factor.challenge` | `TwoFactorChallenge` | web, guest |
| GET | `/verify-email` | `base-tenant.verification.notice` | `Auth\VerifyEmail` | web, auth |
| GET | `/verify-email/{id}/{hash}` | `base-tenant.verification.verify` | `VerifyEmailController` | web, auth, signed, throttle:6,1 |
| GET | `/confirm-password` | `base-tenant.password.confirm` | `Auth\ConfirmPassword` | web, auth |
| GET | `/password/change` | `base-tenant.password.change` | `Auth\ForcePasswordChange` | web, auth |
| POST | `/logout` | `base-tenant.logout` | Closure | web, auth |
| GET | `/invitations/accept/{token}` | `base-tenant.invitations.accept` | `InvitationAcceptController` | web (PUBLIC) |

### Protected Routes (routes/web.php)

Middleware: `config('base-tenant.routes.auth_middleware')` = `['web', 'auth', 'verified', 'base-tenant.subscription']`

| Method | URI | Name | Component | Notes |
|--------|-----|------|-----------|-------|
| GET | `/` | `base-tenant.home` | Closure | Redirects to dashboard (auth) or login (guest) |
| GET | `/dashboard` | `base-tenant.dashboard` | View | `base-tenant::dashboard` |
| GET | `/profile` | `base-tenant.profile` | View | `base-tenant::profile` |
| GET | `/upgrade` | `base-tenant.upgrade` | View | `base-tenant::upgrade` |
| GET | `/users` | `base-tenant.users.index` | `UserManager` | |
| GET | `/users/create` | `base-tenant.users.create` | `EditUser` | |
| GET | `/users/{user}/edit` | `base-tenant.users.edit` | `EditUser` | |
| GET | `/accounts` | `base-tenant.accounts.index` | `AccountManager` | |
| GET | `/accounts/create` | `base-tenant.accounts.create` | `EditAccount` | |
| GET | `/accounts/{account}/edit` | `base-tenant.accounts.edit` | `EditAccount` | |
| GET | `/impersonate/leave` | `impersonate.leave` | Closure | Leaves impersonation, refreshes roles |
| GET | `/invitations` | `base-tenant.invitations.index` | `InvitationManager` | |
| GET | `/activity` | `base-tenant.activity` | `ActivityLog` | |
| GET | `/notifications` | `base-tenant.notifications.index` | `Notifications\Index` | |
| POST | `/notifications/{id}/read` | `base-tenant.notifications.mark-as-read` | `NotificationController@markAsRead` | |
| POST | `/notifications/mark-all-read` | `base-tenant.notifications.mark-all-read` | `NotificationController@markAllAsRead` | |

### Subscription Routes (routes/subscriptions.php)

Only loaded if BOTH `routes.enabled` AND `subscription.enabled` are true.

| Method | URI | Name | Middleware | Notes |
|--------|-----|------|------------|-------|
| GET | `/checkout` | `base-tenant.checkout` | auth, verified, base-tenant.no-subscription | Stripe Checkout redirect |
| GET | `/checkout/success` | `base-tenant.checkout.success` | auth, verified, base-tenant.subscription | Post-payment |
| GET | `/checkout/cancel` | `base-tenant.checkout.cancel` | auth, verified, base-tenant.subscription | Cancelled |
| GET | `/billing` | `base-tenant.billing` | auth, verified, base-tenant.subscription | Stripe Billing Portal redirect |

---

## 8. Livewire Components

### Registration Names

All components are registered with `base-tenant.` prefix in the service provider.

#### Auth Components
| Registration Name | Class | Layout |
|-------------------|-------|--------|
| `base-tenant.auth.login` | `Livewire\Auth\Login` | `base-tenant::layouts.guest` |
| `base-tenant.auth.register` | `Livewire\Auth\Register` | `base-tenant::layouts.guest` |
| `base-tenant.auth.forgot-password` | `Livewire\Auth\ForgotPassword` | `base-tenant::layouts.guest` |
| `base-tenant.auth.reset-password` | `Livewire\Auth\ResetPassword` | `base-tenant::layouts.guest` |
| `base-tenant.auth.confirm-password` | `Livewire\Auth\ConfirmPassword` | `base-tenant::layouts.guest` |
| `base-tenant.auth.verify-email` | `Livewire\Auth\VerifyEmail` | `base-tenant::layouts.guest` |
| `base-tenant.auth.force-password-change` | `Livewire\Auth\ForcePasswordChange` | `base-tenant::layouts.guest` |

#### Management Components
| Registration Name | Class | Layout |
|-------------------|-------|--------|
| `base-tenant.user-manager` | `Livewire\UserManager` | `layouts.app` |
| `base-tenant.edit-user` | `Livewire\EditUser` | `layouts.app` |
| `base-tenant.account-manager` | `Livewire\AccountManager` | `layouts.app` |
| `base-tenant.edit-account` | `Livewire\EditAccount` | `layouts.app` |
| `base-tenant.invitation-manager` | `Livewire\InvitationManager` | `layouts.app` |
| `base-tenant.activity-log` | `Livewire\ActivityLog` | `layouts.app` |

#### Profile Components
| Registration Name | Class | Layout |
|-------------------|-------|--------|
| `base-tenant.profile.update-profile-information-form` | `Livewire\Profile\UpdateProfileInformationForm` | embedded |
| `base-tenant.profile.update-password-form` | `Livewire\Profile\UpdatePasswordForm` | embedded |
| `base-tenant.profile.delete-user-form` | `Livewire\Profile\DeleteUserForm` | embedded |
| `base-tenant.preferences` | `Livewire\Preferences` | embedded |

#### Embedded/Utility Components
| Registration Name | Class | Notes |
|-------------------|-------|-------|
| `base-tenant.account-switcher` | `Livewire\AccountSwitcher` | Handles both single/multi-team modes |
| `base-tenant.two-factor-authentication` | `Livewire\TwoFactorAuthentication` | QR code + recovery codes |
| `base-tenant.two-factor-challenge` | `Livewire\TwoFactorChallenge` | 2FA code entry during login |
| `base-tenant.logout` | `Livewire\Logout` | Logout button |
| `base-tenant.alerts.table` | `Livewire\Alerts\Table` | Alerts display |
| `base-tenant.forms.login-form` | `Livewire\Forms\LoginForm` | Reusable login form |
| `base-tenant.notification-bell` | `Livewire\NotificationBell` | Dropdown with polling |
| `base-tenant.notifications.index` | `Livewire\Notifications\Index` | Full page notifications |

### UI Patterns Used in Components

- **Success/error messages**: `Flux::toast(variant: 'success', heading: '...', text: '...')`
- **Modals**: `$this->modal('modal-name')->show()` / `$this->modal('modal-name')->close()`
- **Full-page layouts**: `#[Layout('layouts.app')]` or `#[Layout('base-tenant::layouts.guest')]`
- **Event dispatch**: `$this->dispatch('event-name')`
- **Page reload**: `$this->js('window.location.reload()')`
- **Polling**: `wire:poll.30s` on notification bell

---

## 9. Notifications

### BaseTenantNotification (Abstract Base Class)

**Namespace**: `Base\Tenant\Notifications\BaseTenantNotification`

**Implements**: `ShouldQueue`

**Required Properties** (set in constructor of extending class):
```php
protected string $title;        // Notification title
protected string $message;      // Notification body
protected string $actionUrl;    // URL for CTA button
protected string $actionText = 'View';  // CTA button text
protected string $icon = 'bell';        // Icon name
protected string $priority = 'low';     // low, medium, high
protected string $category;     // Category for filtering
protected array $causer;        // Who triggered it
protected array $project;       // Related project context
protected array $details = [];  // Additional data
```

**toArray output** (what gets stored in `notifications.data`):
```php
[
    'title' => '...',
    'message' => '...',
    'action_url' => '...',
    'action_text' => '...',
    'icon' => '...',
    'priority' => '...',    // REQUIRED for bell component
    'category' => '...',
    'causer' => [...],
    'project' => [...],
    'details' => [...],
]
```

**NotificationBell expects this exact data structure.** Notifications that don't include `title`, `message`, `priority`, `icon` will render incorrectly.

### Concrete Notifications

| Class | Channel | Purpose |
|-------|---------|---------|
| `InviteUserNotification` | mail | Sends invitation acceptance link |
| `WelcomeUserNotification` | mail | Sends temp credentials on force-password-change user creation |

### Creating New Notifications

```php
class MyNotification extends BaseTenantNotification
{
    public function __construct(Model $subject, User $causer)
    {
        $this->title = 'Something happened';
        $this->message = 'Details about what happened';
        $this->actionUrl = route('...');
        $this->actionText = __('base-tenant::common.view');
        $this->icon = 'document-text';
        $this->priority = 'medium';
        $this->category = 'project.settings';
        $this->causer = ['id' => $causer->id, 'name' => $causer->name];
        $this->project = ['id' => $subject->id, 'name' => $subject->name];
    }
}
```

---

## 10. Configuration Reference

Full config file: `config/base-tenant.php`

### Core

| Key | Type | Default | Env Var | Description |
|-----|------|---------|---------|-------------|
| `multi_team` | bool | false | `BASE_TENANT_MULTI_TEAM` | Enable multi-team mode |
| `home_url` | string | 'base-tenant.dashboard' | `BASE_TENANT_HOME_URL` | Post-login redirect route name |

### Subscription

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `subscription.enabled` | bool | true | `BASE_TENANT_SUBSCRIPTION_ENABLED` |
| `subscription.default_product` | ?string | null | `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT` |
| `subscription.default_price` | ?string | null | `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE` |
| `subscription.success_url` | string | 'base-tenant.checkout.success' | `BASE_TENANT_SUBSCRIPTION_SUCCESS_URL` |
| `subscription.cancel_url` | string | 'base-tenant.checkout.cancel' | `BASE_TENANT_SUBSCRIPTION_CANCEL_URL` |
| `subscription.trial_days` | int | 14 | `BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS` |

### Plans

Structure per plan:
```php
'plans' => [
    'plan_key' => [
        'name' => 'Display Name',
        'stripe_price_id' => env('STRIPE_PLAN_PRICE_ID'),
        'features' => [
            'feature_name' => value,  // bool, int, or -1 for unlimited
        ],
    ],
],
```

Feature value semantics:
- `true` / `false` — boolean gate
- `-1` — unlimited (always passes limit checks)
- `0` — disabled (fails all checks)
- `>0` — numeric limit

### Notifications

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `notifications.enabled` | bool | true | `BASE_TENANT_NOTIFICATIONS_ENABLED` |
| `notifications.channels` | array | ['database'] | - |
| `notifications.polling_interval` | int | 30 | `BASE_TENANT_NOTIFICATIONS_POLLING_INTERVAL` |
| `notifications.dropdown_limit` | int | 10 | - |
| `notifications.per_page` | int | 25 | - |
| `notifications.categories` | array | (see config) | - |
| `notifications.ai_usage_threshold` | int | 50 | - |
| `notifications.quota_warning_percent` | int | 80 | - |

### Activity Log

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `activity_log.enabled` | bool | true | `BASE_TENANT_ACTIVITY_LOG_ENABLED` |
| `activity_log.retention_days` | int | 90 | `BASE_TENANT_ACTIVITY_LOG_RETENTION` |

### Roles

Structure:
```php
'roles' => [
    'system' => [
        ['key' => 'administrator', 'name' => 'Administrator', 'is_system' => true],
        ['key' => 'administrator-finance', 'name' => 'Administrator Finance', 'is_system' => true],
        ['key' => 'administrator-tech', 'name' => 'Administrator Tech', 'is_system' => true],
    ],
    'customer' => [
        ['key' => 'customer-admin', 'name' => 'Customer Admin', 'is_system' => false],
        ['key' => 'customer-user', 'name' => 'Customer User', 'is_system' => false],
        ['key' => 'customer-viewer', 'name' => 'Customer Viewer', 'is_system' => false],
        ['key' => 'customer-finance', 'name' => 'Customer Finance', 'is_system' => false],
    ],
    'custom' => [],  // Host app adds here
],
```

### Models

| Key | Default | Env Var |
|-----|---------|---------|
| `models.user` | `Base\Tenant\Models\User` | `BASE_TENANT_USER_MODEL` |
| `models.account` | `Base\Tenant\Models\Account` | `BASE_TENANT_ACCOUNT_MODEL` |
| `models.role` | `Base\Tenant\Models\Role` | `BASE_TENANT_ROLE_MODEL` |
| `models.user_invite` | `Base\Tenant\Models\UserInvite` | `BASE_TENANT_USER_INVITE_MODEL` |

### Routes

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `routes.enabled` | bool | true | `BASE_TENANT_ROUTES_ENABLED` |
| `routes.prefix` | string | '' | `BASE_TENANT_ROUTES_PREFIX` |
| `routes.middleware` | array | ['web'] | - |
| `routes.auth_middleware` | array | ['web', 'auth', 'verified', 'base-tenant.subscription'] | - |

### UI

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `ui.brand_name` | string | config('app.name') | `APP_NAME` |
| `ui.brand_logo` | ?string | null | `BASE_TENANT_BRAND_LOGO` |

### Force Password Change

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `force_password_change.enabled` | bool | false | `BASE_TENANT_FORCE_PASSWORD_CHANGE` |
| `force_password_change.send_welcome_email` | bool | true | `BASE_TENANT_SEND_WELCOME_EMAIL` |

### Invitations

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `invitations.enabled` | bool | true | `BASE_TENANT_INVITATIONS_ENABLED` |
| `invitations.expires_in_days` | int | 7 | `BASE_TENANT_INVITATIONS_EXPIRES` |

### Settings

| Key | Type | Default | Env Var |
|-----|------|---------|---------|
| `settings.enabled` | bool | true | `BASE_TENANT_SETTINGS_ENABLED` |

---

## 11. Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `BASE_TENANT_MULTI_TEAM` | false | Enable multi-team mode |
| `BASE_TENANT_HOME_URL` | 'base-tenant.dashboard' | Post-login redirect route |
| `BASE_TENANT_SUBSCRIPTION_ENABLED` | true | Enable Stripe billing |
| `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT` | null | Stripe product ID |
| `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE` | null | Stripe price ID |
| `BASE_TENANT_SUBSCRIPTION_SUCCESS_URL` | 'base-tenant.checkout.success' | Route after payment |
| `BASE_TENANT_SUBSCRIPTION_CANCEL_URL` | 'base-tenant.checkout.cancel' | Route on cancel |
| `BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS` | 14 | Trial period |
| `BASE_TENANT_NOTIFICATIONS_ENABLED` | true | Enable notifications |
| `BASE_TENANT_NOTIFICATIONS_POLLING_INTERVAL` | 30 | Bell poll seconds |
| `BASE_TENANT_ACTIVITY_LOG_ENABLED` | true | Enable audit trail |
| `BASE_TENANT_ACTIVITY_LOG_RETENTION` | 90 | Days to retain logs |
| `BASE_TENANT_FORCE_PASSWORD_CHANGE` | false | Force new password on first login |
| `BASE_TENANT_SEND_WELCOME_EMAIL` | true | Send credentials email |
| `BASE_TENANT_INVITATIONS_ENABLED` | true | Enable invitations |
| `BASE_TENANT_INVITATIONS_EXPIRES` | 7 | Days until invite expires |
| `BASE_TENANT_SETTINGS_ENABLED` | true | Enable settings system |
| `BASE_TENANT_ROUTES_ENABLED` | true | Load package routes |
| `BASE_TENANT_ROUTES_PREFIX` | '' | URL prefix |
| `BASE_TENANT_BRAND_LOGO` | null | Custom logo URL |
| `BASE_TENANT_USER_MODEL` | (package default) | Override User model |
| `BASE_TENANT_ACCOUNT_MODEL` | (package default) | Override Account model |
| `BASE_TENANT_ROLE_MODEL` | (package default) | Override Role model |
| `BASE_TENANT_USER_INVITE_MODEL` | (package default) | Override UserInvite model |
| `STRIPE_STARTER_PRICE_ID` | - | Stripe price for starter plan |
| `STRIPE_PROFESSIONAL_PRICE_ID` | - | Stripe price for professional plan |

Stripe/Cashier (required when subscriptions enabled):
| Variable | Description |
|----------|-------------|
| `STRIPE_KEY` | Stripe publishable key |
| `STRIPE_SECRET` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret |

---

## 12. Artisan Commands

### `k2labs-base:install`

Interactive package installer. Phases:
1. Collect options (multi-team, subscriptions, test user, API keys)
2. Preview changes and confirm
3. Execute: resolve conflicts, publish config, update .env, run migrations, seed roles, create admin user

Creates `admin@example.com` / `secret123`. Optionally creates `test@example.com` / `password`.

### `k2labs-base:sync-roles`

Reads `config('base-tenant.roles')` (system + customer + custom) and syncs to database via `updateOrCreate` by `key`.

```bash
php artisan k2labs-base:sync-roles
```

### `k2labs-base:prune-activity-log`

Deletes activity log entries older than retention period.

```bash
php artisan k2labs-base:prune-activity-log          # Uses config retention_days (90)
php artisan k2labs-base:prune-activity-log --days=30  # Custom
```

---

## 13. Blade Directives & View Components

### Custom Blade Directives

```blade
@feature('api_access')
    {{-- Only shown if current account's plan has api_access --}}
@endfeature

@impersonating
    {{-- Only shown while impersonating another user --}}
@endImpersonating
```

The `@feature` directive reads `session('current_account_id')` and calls `FeatureService::accountCan()`.

### Layout Components

```blade
<x-base-tenant::app-layout>    {{-- Authenticated pages --}}
<x-base-tenant::guest-layout>  {{-- Public/auth pages --}}
```

### Anonymous Components

Registered from `resources/views/components/`. Available without prefix in package views. Used within the package's own Blade templates.

---

## 14. Translation System

### Namespace

All translations use the `base-tenant::` namespace:
```php
__('base-tenant::common.save')
__('base-tenant::users.create')
```

### Translation Files (resources/lang/{locale}/)

| File | Content |
|------|---------|
| `accounts.php` | Account management strings |
| `app.php` | General app strings |
| `auth.php` | Authentication strings |
| `common.php` | Shared UI strings (save, cancel, delete, etc.) |
| `currencies.php` | Currency display names |
| `languages.php` | Language display names |
| `mails.php` | Email content strings |
| `pagination.php` | Pagination strings |
| `passwords.php` | Password reset strings |
| `roles.php` | Role display names |
| `subscription.php` | Billing/subscription strings |
| `users.php` | User management strings |
| `validation.php` | Validation messages |
| `welcome.php` | Welcome/onboarding strings |

### Available Languages

- `en` (English)
- `es` (Spanish)

### Adding a New Language

1. Create directory `resources/lang/{locale}/`
2. Copy all files from `resources/lang/en/`
3. Translate values
4. Update `Preferences` component `locales()` computed property

---

## 15. Helper Functions

### `tenant_setting(string $key, mixed $default = null): mixed`

Autoloaded via `src/helpers.php`. Calls `SettingService::resolve()`.

Cascade: authenticated user setting > current account setting > default.

```php
$timezone = tenant_setting('timezone', 'UTC');
```

---

## 16. Exceptions

### NoAccountException (`Base\Tenant\Exceptions\NoAccountException`)

**Extends**: `RuntimeException`

**Static Factory**:
```php
NoAccountException::userHasNoAccounts()
// Message: "User has no accounts. Please contact support or complete onboarding."
```

**Thrown by**: `User::determineDefaultAccount()` when user has no last_account_id, no account_id, and no accounts in pivot table.

---

## 17. Conventions & Patterns

These patterns MUST be followed in all new code:

1. **UUIDs everywhere**: All models use `HasUuids`. Never use auto-increment IDs.
2. **$guarded = ['id']**: All models use guarded, never $fillable.
3. **Soft deletes**: User and Account use SoftDeletes.
4. **declare(strict_types=1)**: Every PHP file starts with this.
5. **Service layer**: Business logic in `src/Services/` with static methods. Not in controllers/components.
6. **Config-driven model resolution**: Always use `config('base-tenant.models.xxx', Default::class)` in relationships.
7. **Session-based tenant context**: Get current account from `session('current_account_id')`. NEVER from `auth()->user()->account_id` (that's the primary, not necessarily the current).
8. **Flux::toast() for UI feedback**: `Flux::toast(variant: 'success', heading: '...', text: '...')`. Never `session()->flash()` in Livewire.
9. **Modal control**: `$this->modal('name')->show()` / `$this->modal('name')->close()`.
10. **Layout attributes**: Full-page components use `#[Layout('layouts.app')]`. Auth pages use `#[Layout('base-tenant::layouts.guest')]`.
11. **Translation keys**: Always `__('base-tenant::file.key')`. Never hard-code strings.
12. **Role checks**: Use `$user->hasRole('key')` / `$user->hasAnyRole([...])` / `$user->authorizeRoles([...])`. These read from session cache.
13. **System admin detection**: `$user->is_admin || is_null($user->account_id)`.
14. **Multi-team awareness**: Check `config('base-tenant.multi_team', false)` before deciding how to query accounts/users.
15. **Route naming**: All routes use `base-tenant.` prefix.
16. **Component naming**: All Livewire components use `base-tenant.` prefix.
17. **Account scoping**: Always filter by `session('current_account_id')` for non-admin queries.
18. **Role attachment with account_id**: When attaching roles, include `account_id` in pivot data for account-scoped roles: `$user->roles()->attach($roleId, ['account_id' => $accountId])`.
19. **Feature checks in Blade**: Use `@feature('name')` directive. In PHP: `$account->planCan('name')`.

---

## 18. DO NOT Rules

These are explicit prohibitions to prevent architectural errors:

1. **DO NOT** put `Billable` trait on User. It belongs ONLY on Account.
2. **DO NOT** create your own auth routes. The package provides complete authentication.
3. **DO NOT** create a new User model from scratch. Extend `Base\Tenant\Models\User`.
4. **DO NOT** use `$fillable` on models. The package uses `$guarded = ['id']`.
5. **DO NOT** use auto-increment IDs. Everything uses UUIDs via `HasUuids`.
6. **DO NOT** use `auth()->user()->account_id` to get current tenant context. Use `session('current_account_id')`. The `account_id` column is the PRIMARY account, not necessarily the currently active one.
7. **DO NOT** query the database for role checks. Use `$user->hasRole()` which reads from session cache (faster, already filtered by current account).
8. **DO NOT** manually create accounts without calling `createPrimaryAccountAndSetRole()` for new users. This method handles: account creation, pivot attachment, role assignment, trial period.
9. **DO NOT** attach roles without `account_id` in the pivot for non-admin users. Global roles (null account_id) are only for system administrators.
10. **DO NOT** create migrations that modify the package's existing tables. Create new tables or add columns to your own models.
11. **DO NOT** override package route files. Add your own routes separately in your app's `routes/web.php`.
12. **DO NOT** use `session()->flash()` in Livewire components. Use `Flux::toast()`.
13. **DO NOT** create middleware that duplicates existing subscription/feature/locale/account-context checks. The package already handles all of this.
14. **DO NOT** put business logic in Livewire components or controllers. Use the Service layer.
15. **DO NOT** forget to handle both `multi_team = true` and `multi_team = false` cases when writing queries that involve user-account relationships.
16. **DO NOT** hard-code role keys that don't exist in config. Always verify via `Role::roleExists($key)`.
17. **DO NOT** send notifications without extending `BaseTenantNotification`. The bell component expects the specific `toArray()` data structure (title, message, priority, icon, action_url).
18. **DO NOT** create duplicate activity logging. Models with `LogsActivity` trait already auto-log create/update/delete. Only use `ActivityLogService::log()` directly for custom actions.
19. **DO NOT** publish package views to vendor. The service provider explicitly discourages this. Extend Livewire components or use Blade slots instead.

---

## 19. How to Extend

### Adding a New Model with Tenant Scoping

```php
// 1. Migration
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    // ... other columns
    $table->timestamps();
    $table->softDeletes();
});

// 2. Model
namespace App\Models;

use Base\Tenant\Traits\HasSettings;
use Base\Tenant\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasUuids, LogsActivity, HasSettings, SoftDeletes;

    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(config('base-tenant.models.account'));
    }
}

// 3. Always scope queries by current account
$projects = Project::where('account_id', session('current_account_id'))->get();
```

### Adding a New Livewire Component (Full Page)

```php
namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectManager extends Component
{
    public function mount(): void
    {
        // Authorization check
        auth()->user()->authorizeRoles(['customer-admin', 'administrator']);
    }

    public function delete(string $id): void
    {
        $project = Project::where('account_id', session('current_account_id'))
            ->findOrFail($id);

        $project->delete();

        Flux::toast(variant: 'success', heading: __('base-tenant::common.success'), text: __('projects.deleted'));
    }

    public function render()
    {
        return view('livewire.project-manager', [
            'projects' => Project::where('account_id', session('current_account_id'))->paginate(),
        ]);
    }
}
```

### Adding a New Route

Add to your app's `routes/web.php` (not the package routes):

```php
use App\Livewire\ProjectManager;

Route::middleware(config('base-tenant.routes.auth_middleware'))
    ->group(function () {
        Route::get('/projects', ProjectManager::class)->name('projects.index');
    });
```

### Adding a New Role

1. Add to `config/base-tenant.php`:
```php
'custom' => [
    ['key' => 'project-manager', 'name' => 'Project Manager', 'is_system' => false],
],
```

2. Run: `php artisan k2labs-base:sync-roles`

### Adding a New Plan Feature

1. Add to all plans in `config/base-tenant.php`:
```php
'features' => [
    'advanced_reporting' => true,  // or false, or numeric limit
],
```

2. Use in code:
```php
// Middleware
Route::middleware('base-tenant.feature:advanced_reporting')->...

// Blade
@feature('advanced_reporting') ... @endfeature

// PHP
$account->planCan('advanced_reporting')
```

### Adding a New Notification

```php
namespace App\Notifications;

use Base\Tenant\Notifications\BaseTenantNotification;

class ProjectUpdatedNotification extends BaseTenantNotification
{
    public function __construct($project, $causer)
    {
        $this->title = __('notifications.project_updated');
        $this->message = __('notifications.project_updated_message', ['name' => $project->name]);
        $this->actionUrl = route('projects.show', $project);
        $this->actionText = __('base-tenant::common.view');
        $this->icon = 'pencil';
        $this->priority = 'medium';
        $this->category = 'project.settings';
        $this->causer = ['id' => $causer->id, 'name' => $causer->name];
        $this->project = ['id' => $project->id, 'name' => $project->name];
    }
}
```

### Adding Activity Logging to a Model

Just add the trait:
```php
use Base\Tenant\Traits\LogsActivity;

class Project extends Model
{
    use LogsActivity;
    // Automatically logs created, updated, deleted events
}
```

### Adding Settings to a Model

```php
use Base\Tenant\Traits\HasSettings;

class Project extends Model
{
    use HasSettings;
}

// Usage:
$project->setSetting('default_language', 'en', 'string', 'general');
$project->getSetting('default_language', 'en');
$project->getSettingsByGroup('general');
```

### Overriding a Package Model

1. Create your model extending the package model:
```php
namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;

class User extends BaseTenantUser
{
    // Add your methods and relationships
}
```

2. Update config:
```php
// config/base-tenant.php
'models' => [
    'user' => App\Models\User::class,
],
```

---

## 20. File Structure

```
src/
├── BaseTenantServiceProvider.php
├── helpers.php
├── Console/
│   ├── Commands/
│   │   ├── InstallCommand.php
│   │   ├── PruneActivityLogCommand.php
│   │   └── SyncRolesCommand.php
│   └── Support/
│       ├── ConflictDetector.php
│       ├── EnvironmentManager.php
│       ├── MigrationRunner.php
│       └── UserModelManager.php
├── Exceptions/
│   └── NoAccountException.php
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── Auth/VerifyEmailController.php
│   │   ├── CheckoutController.php
│   │   ├── InvitationAcceptController.php
│   │   └── NotificationController.php
│   └── Middleware/
│       ├── DoesNotHaveSubscription.php
│       ├── EnsurePasswordChanged.php
│       ├── HasFeature.php
│       ├── HasSubscription.php
│       ├── SetAccountContext.php
│       └── SetLocale.php
├── Livewire/
│   ├── AccountManager.php
│   ├── AccountSwitcher.php
│   ├── ActivityLog.php
│   ├── EditAccount.php
│   ├── EditUser.php
│   ├── InvitationManager.php
│   ├── Logout.php
│   ├── NotificationBell.php
│   ├── Preferences.php
│   ├── TwoFactorAuthentication.php
│   ├── TwoFactorChallenge.php
│   ├── UserManager.php
│   ├── Actions/Logout.php
│   ├── Alerts/Table.php
│   ├── Auth/
│   │   ├── ConfirmPassword.php
│   │   ├── ForcePasswordChange.php
│   │   ├── ForgotPassword.php
│   │   ├── Login.php
│   │   ├── Register.php
│   │   ├── ResetPassword.php
│   │   └── VerifyEmail.php
│   ├── Forms/LoginForm.php
│   ├── Notifications/Index.php
│   └── Profile/
│       ├── DeleteUserForm.php
│       ├── UpdatePasswordForm.php
│       └── UpdateProfileInformationForm.php
├── Models/
│   ├── Account.php
│   ├── ActivityLog.php
│   ├── Role.php
│   ├── Setting.php
│   ├── User.php
│   └── UserInvite.php
├── Notifications/
│   ├── BaseTenantNotification.php
│   ├── InviteUserNotification.php
│   └── WelcomeUserNotification.php
├── Services/
│   ├── ActivityLogService.php
│   ├── FeatureService.php
│   ├── InvitationService.php
│   ├── NotificationService.php
│   └── SettingService.php
├── Traits/
│   ├── HasExtensibleRoles.php
│   ├── HasSettings.php
│   └── LogsActivity.php
└── View/
    └── Components/
        ├── AppLayout.php
        └── GuestLayout.php
```

---

## 21. Third-Party Dependencies

| Package | Purpose | Key API |
|---------|---------|---------|
| `laravel/cashier` ^16.0 | Stripe subscriptions | `Billable` trait on Account. `$account->newSubscription()`, `$account->subscriptions()->active()`, `$account->redirectToBillingPortal()` |
| `laravel/sanctum` ^4.0 | API token auth | `personal_access_tokens` table. Token-based API auth. |
| `livewire/livewire` ^3.0 | Reactive components | All UI components. Wire attributes, Livewire lifecycle. |
| `livewire/flux` + `flux-pro` ^2.4 | UI library | `Flux::toast()`, modal system, form components. |
| `pragmarx/google2fa-laravel` ^2.3 | TOTP 2FA | `Google2FA::generateSecretKey()`, `Google2FA::verifyKey()` |
| `bacon/bacon-qr-code` ^3.0 | QR codes | SVG QR generation for 2FA setup |
| `lab404/laravel-impersonate` ^1.7 | User impersonation | `Impersonate` trait. `$user->impersonate($other)`, `$user->leaveImpersonation()`, `canImpersonate()`, `canBeImpersonated()`, `@impersonating` directive |
| `laravel/vapor-core` ^2.37 | Cloud deploy | AWS Lambda/Vapor compatibility |
| `spatie/laravel-flare` ^2.2 | Error tracking | Exception monitoring |

---

## 22. Session Keys

| Key | Set By | Contains |
|-----|--------|----------|
| `current_account_id` | `SetAccountContext` middleware | UUID of active tenant account |
| `user.roles` | `User::storeRolesSession()` | Array of role keys for current account context |
| `locale` | `SetLocale` middleware / `Preferences` component | User's locale (e.g., 'en', 'es') |
| `2fa.user_id` | 2FA challenge flow | User ID during 2FA verification |
| `2fa.remember` | 2FA challenge flow | Remember-me preference during 2FA |
| `pending_invite_token` | Invitation accept flow | Token for pending invite during login/register |

---

## 23. Non-Production Behavior

### Subscription Bypass

In non-production environments (`local`, `testing`), if Stripe is NOT fully configured (missing any of `cashier.key`, `cashier.secret`, `subscription.default_product`, `subscription.default_price`):

- `HasSubscription` middleware: **allows all requests through** (no redirect to checkout)
- `DoesNotHaveSubscription` middleware: **redirects to dashboard** (prevents accessing checkout)

This means local development works without Stripe setup.

### Checkout Route

Even with bypass, the checkout route itself validates Stripe config and returns 503 if not configured:
```
abort(503, __('base-tenant::subscription.not_configured'))
```

---

## 24. Testing Reference

### Factories

| Factory | Creates | States |
|---------|---------|--------|
| `UserFactory` | User | Default, `admin()`, `unverified()` |
| `AccountFactory` | Account | Default |

### Seeders

| Seeder | Does |
|--------|------|
| `BaseTenantSeeder` | Calls InitialLoadSeeder |
| `InitialLoadSeeder` | Syncs roles from config |
| `TestUserSeeder` | Creates test@example.com with password 'password' |

### Default Test Credentials

| Email | Password | Role |
|-------|----------|------|
| admin@example.com | secret123 | administrator (created by install command) |
| test@example.com | password | customer-admin (created by TestUserSeeder) |

### PHPUnit Configuration

**Critical**: Verify `phpunit.xml` uses SQLite in-memory:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Running tests against the development database will WIPE ALL DATA.

---

## End of Document

This file is the single source of truth for LLMs working with the base-tenant package. When in doubt, read the actual source code at the paths listed in the [File Structure](#20-file-structure) section.
