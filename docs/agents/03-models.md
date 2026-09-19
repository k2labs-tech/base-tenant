# Models — what the package owns in the database

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-18

## When to use this

Before you touch a package table, extend a package model, or write a query
against one. It is the list of what already exists, which columns are real,
and which of them a host application may rely on.

## When NOT to use this

Models the host application defines for its own domain. Those only need
`BelongsToAccount` ([01](01-architecture.md)) and, if they hold files,
`HasFiles` ([11](11-files.md)). Nothing here has to be subclassed.

---

## Every model

Every model uses `HasUuids`, so it is left out of the traits column below.
`BelongsToAccount` means the model carries the tenant global scope.

| Class (`Base\Tenant\Models\`) | Table | Traits | What it is |
|---|---|---|---|
| `Account` | `accounts` | Billable, HasFactory, HasSettings, SoftDeletes | The tenant. Also the Cashier customer. |
| `User` | `users` | HasFactory, HasRolesAndPermissions, HasSettings, Impersonate, Notifiable, PurgesPersonalData, SoftDeletes | A person. Belongs to one primary account and to many. |
| `Role` | `roles` | HasExtensibleRoles, HasFactory | A named set of permissions, global or owned by one account. |
| `Permission` | `permissions` | HasFactory | One granular ability. Always global. |
| `Feature` | `features` | — | A per-account override of a plan feature. |
| `File` | `files` | BelongsToAccount, SoftDeletes | One stored file. |
| `Setting` | `settings` | — | One key/value on a morph target (account or user). |
| `Menu` | `menus` | — | A named navigation tree. |
| `MenuItem` | `menu_items` | — | One entry in it, product-wide or per account. |
| `ActivityLog` | `activity_log` | BelongsToAccount | Who did what to which record. |
| `UserInvite` | `user_invites` | BelongsToAccount, HasFactory | A pending invitation to join an account. |
| `UsageCounter` | `usage_counters` | — | Running total of one metric, account, period. |
| `UsageEvent` | `usage_events` | — | One movement of a counter. |
| `DataTransfer` | `data_transfers` | BelongsToAccount | One import or export and how it went. |
| `AccountConnection` | `account_connections` | BelongsToAccount | One account's credentials at a third party. |
| `OutboundWebhook` | `outbound_webhooks` | BelongsToAccount | An endpoint subscribed to events. |
| `OutboundWebhookDelivery` | `outbound_webhook_deliveries` | BelongsToAccount | One attempt at one event. |
| `Sequence` | `sequences` | — | A correlative counter. |
| `Language` | `languages` | — | A language the installation offers. |
| `SocialAccount` | `social_accounts` | — | An external identity linked to a user. |
| `EmailSuppression` | `email_suppressions` | — | An address nothing may be sent to. |
| `WaitlistSignup` | `waitlist_signups` | — | Someone who asked to be told at launch. |

Note `activity_log`, singular: Eloquent would have guessed `activity_logs`, so
the model sets `$table`. **No package model uses `LogsActivity`** — that trait
is for host models; the log table itself is written by `ActivityLogService`.

---

## Tenant-scoped, and deliberately not

Tenant data that carries `account_id` but **no** tenant scope:

- `UsageCounter`, `UsageEvent` — nothing writes them through Eloquent. The
  value moves in one atomic statement inside `UsageStore`, and reporting reads
  across accounts.
- `Feature` — `FeatureService::overridesFor($account)` filters by `account_id`
  itself, and reads overrides for accounts other than the one in context.
- `Menu`, `MenuItem`, `Role` — a null `account_id` means "the product's own",
  shared by every tenant. A scope by equality would hide it from everyone;
  `Role::assignable()` is a union instead.

Global on purpose, because they are facts about the installation or about a
person rather than about a customer:

- `Language` — the catalogue belongs to the installation. Which subset an
  account exposes is a setting on that account.
- `EmailSuppression` — a hard bounce is a fact about the address. A second
  account mailing the same dead mailbox burns the same sending reputation.
- `WaitlistSignup` — they have no account yet, and that is the point.
- `SocialAccount` — the link is to the person, who may belong to several
  accounts.
- `Permission` — every account draws from the same catalogue; it is the roles
  that differ.
- `Sequence` — per account *or* global, in one table, via a sentinel value.

---

## Account

```
id  name  active  email  phone  address  city  state  country  postal_code  vat
user_id (owner)  domain (unique)  subdomain (unique)  status (default 'active')
stripe_id  pm_type  pm_last_four  trial_ends_at
onboarded_at  force_password_change  daily_notification_summary
timestamps  deleted_at
```

Only two casts: `force_password_change` boolean and `onboarded_at` datetime.
`active` and `daily_notification_summary` carry none, so they arrive as
whatever the driver returns. `force_password_change` is nullable and that is
meaningful: null inherits the global setting, `false` is an explicit opt-out.

```php
$account->users;                    // BelongsToMany, via account_user
$account->owner;                    // BelongsTo user_id
$account->settings;                 // MorphMany, from HasSettings
$account->subscriptions;            // from Cashier's Billable
$account->hasActiveSubscription();
$account->planCan('api_access');
$account->planLimit('max_users');            // -1 is unlimited
$account->isWithinPlanLimit('max_users', $count);
$account->getPlanName();
```

No query scopes. `domain`/`subdomain` are what `DomainTenantResolver` reads.

---

## User

```
id  account_id (primary)  last_account_id  name  email (unique)  email_verified_at
password (nullable)  must_change_password  two_factor_secret
two_factor_recovery_codes  two_factor_confirmed_at
locale  currency  decimal_places  timezone  default_locale
date_format  time_format  decimals_number  decimals_separator  thousands_separator
phone  personal_email  personal_phone  is_admin  accessed_at
daily_notification_summary (nullable)  terms_accepted_at  terms_version
remember_token  timestamps  deleted_at
```

```php
protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

// casts(): email_verified_at, accessed_at, two_factor_confirmed_at => datetime
//          password => hashed;  decimal_places => integer
//          is_admin, must_change_password => boolean
//          two_factor_recovery_codes => array
```

`is_admin` is cast because without it the column arrives as `'0'` on some
drivers, and `'0'` is a non-empty string: any check written without `(bool)`
would hand platform-admin rights to someone who has none. `password` is
nullable since social login: a user who signs in with Google has
no password, and a random one is a credential nobody knows that still shows up
in every reset flow. `two_factor_secret` is written encrypted and decrypted by
an accessor, so the attribute reads plain and the column never holds it.

```php
$user->account;                     // BelongsTo, the primary account
$user->accounts;                    // BelongsToMany, withTimestamps
$user->roles;  $user->permissions;  // spatie, scoped to the account in context
$user->settings;                    // MorphMany
User::inAccount($account)->get();   // pivot members, plus users whose account_id matches

$user->hasPermission('users.update');        // false, not an exception, when unknown
$user->hasPermissionInAccount($other, 'users.update');
$user->rolesForAccount($other);
$user->addRole('customer-admin');            // by name, within the account in context
$user->isSuperAdmin();  $user->belongsToAccount($account);
$user->determineDefaultAccount();            // last_account_id > account_id > first
$user->shouldReceiveDailySummary();          // user preference, else account default
$user->initials;                             // accessor
```

Two columns exist for decimal precision — `decimals_number` from the original
table, `decimal_places` added later — and only `decimal_places` is read.
Likewise `applyDateTimeZoneFormat()` reads `$this->hour_format`, and no such
column exists: the table has `time_format`. Pass `$format` explicitly there.

---

## Role and Permission

```
roles:        id  account_id (nullable)  key  name  display_name  guard_name
              description  is_system  timestamps
              UNIQUE (account_id, name, guard_name)
permissions:  id  name  guard_name  group  display_name  timestamps
              UNIQUE (name, guard_name)
```

`name` is the identifier spatie works with, `display_name` is the label, and
`key` is kept as a synced alias for host code that still queries it. The sync
is a `name` mutator, not a `saving` listener, because model events can be
silenced — Laravel's own `DatabaseSeeder` ships with `WithoutModelEvents`, and
under it `key`, which is NOT NULL, was never populated and seeding died.

```php
Role::assignable()->get();    // global catalogue + the account in context
Role::manageable()->get();    // what the editor lists: staff see assignable,
                              // everyone else only their own account's roles
Role::system()->get();  Role::nonSystem()->get();     // is_system
$role->account;               // null for a global role
$role->isGlobal();  $role->label;   // label = display_name, falling back to name

Permission::inGroup('users')->get();
Permission::configured();          // Collection keyed by group, from config
Permission::allConfiguredNames();  // flat list
```

Assignments live in spatie's `model_has_roles` and `model_has_permissions`,
both keyed by `(account_id, …, model_id, model_type)`. **`role_user` is the v1
pivot and nothing writes to it any more**; the upgrade migration copies its
rows across — those with no account onto `TenantManager::SYSTEM_TEAM_ID` — and
leaves the table behind so the change is reversible by hand.

---

## Feature

```
id  account_id  key  value (text)  type (default 'boolean')  expires_at  timestamps
UNIQUE (account_id, key)
```

```php
Feature::active()->get();     // expires_at null, or still in the future
$feature->casted_value;       // decoded per `type`: boolean|integer|json|array
$feature->isExpired();
Feature::serializeValue($value, $type);   // the inverse, for writes

// Write through the facade, never the model:
Feature::for($account)->set('max_users', 25, $expiresAt);
```

## File

```
id  account_id  fileable_type/fileable_id (nullable morph)  collection
disk  path  name  extension  mime_type  size  checksum
variants (json)  custom_properties (json)  order_column  uploaded_by
timestamps  deleted_at
```

```php
// casts: size, order_column => integer; variants, custom_properties => array
$file->fileable;                       // MorphTo, null for library files
$file->uploader;                       // BelongsTo, uploaded_by
File::inCollection('photos')->ordered()->get();
$file->url();  $file->variantUrl('thumb');   // signed, 5 minutes by default
$file->isImage();  $file->humanSize();  $file->paths();  $file->directory();
```

Full flow in [11-files.md](11-files.md). Never create rows here by hand:
`FileStore` also moves the bytes and moves the storage meter.

## Setting

```
id  settingable_type/settingable_id  group (default 'general')  key
value (text)  type (default 'string')  timestamps
UNIQUE (settingable_type, settingable_id, key)
```

The unique index does **not** include `group`: a key is unique per owner, and
moving it between groups does not create a second row. `type` is a column, not
a cast — `casted_value` decodes on read and `Setting::serializeValue()` encodes
on write, which is why writing without a type stores a string. Reached through
`HasSettings` (on `Account` and `User`) or, for typed setting classes, through
the `Settings` facade — see [02](02-conventions.md).

## Menu and MenuItem

```
menus:      id  account_id (nullable)  key  name        UNIQUE (account_id, key)
menu_items: id  menu_id  parent_id  account_id (nullable)  key  label  icon
            route  route_params (json)  url  target  permission  feature  badge
            position  is_active  is_system  meta (json)
            UNIQUE (menu_id, account_id, key)
```

```php
// MenuItem casts: is_active, is_system => bool; position => int; route_params, meta => array
$menu->items;  $menu->rootItems;          // both ordered by position
$item->parent;  $item->children;  $item->account;
MenuItem::global()->get();                // account_id is null: the product menu
MenuItem::forAccount($accountId)->get();
$item->isOverride();                      // account row shadowing a system entry
```

`label` stores a translation key, never a translated string — otherwise the
account is stuck in whatever language `k2labs-base:sync-menus` ran in.

## ActivityLog

```
id  account_id (nullable)  causer_type/causer_id  subject_type/subject_id
action  description  properties (json)  ip_address  user_agent  timestamps
```

There are no `old_values`/`new_values` columns. Both live inside `properties`:

```php
$log->old;  $log->new;                    // properties['old'], properties['new']
$log->changed_fields;                     // array_keys(properties['new'])
$log->causer;  $log->subject;             // MorphTo
ActivityLog::byAction('updated')->get();
ActivityLog::forSubject($booking)->get();
```

`account_id` is nullable so platform-level actions are recorded at all.

## UserInvite

```
id  email (unique)  token (unique, 50)  account_id  role_id  invited_by
expires_at  accepted_at  timestamps
```

**`email` is unique across the whole table**, not per account: one address
cannot hold two open invitations, even to different accounts.

```php
// casts: expires_at, accepted_at => datetime
$invite->role;  $invite->invitedBy;  $invite->account;
UserInvite::pending()->get();    // never accepted, not yet expired
UserInvite::expired()->get();    // never accepted, past expires_at
UserInvite::accepted()->get();
$invite->isPending();  $invite->isExpired();  $invite->isAccepted();
```

`expired()` filters on `accepted_at` too: an invitation accepted months ago
also has a past date, and it is not the same thing.

---

## Module models, briefly

| Model | Worth knowing |
|---|---|
| `UsageCounter` | `(account_id, metric, period)` unique. `period` is `''` and never null for metrics that do not reset, because a unique index treats two NULLs as distinct and would allow a second counter nobody reads. `notified_threshold` stops a warning firing on every increment. |
| `UsageEvent` | `UPDATED_AT = null`. `delta` is signed, so events sum to the counter. `reported_at` is stamped once billing has it, so a run that dies repeats nothing. |
| `DataTransfer` | `file()` and `errorFile()` both point at `files`. `progress()` returns null, not 0, when the total is unknown — a bar at 0% reads as stuck. Constants: `IMPORT`, `EXPORT`, `PENDING`…`FAILED`. Scopes `imports()`, `exports()`. |
| `AccountConnection` | `credentials` is `encrypted:array` and hidden. Unique on `(account_id, provider, label)`, so an account can hold two of the same provider. Scope `enabled()`. |
| `OutboundWebhook` | `secret` encrypted and hidden, `events` an array of patterns. `wants($event)` matches `*` and `booking.*`. Disabled after `FAILURE_LIMIT` (20) consecutive failures. |
| `OutboundWebhookDelivery` | `BACKOFF` = 60, 300, 1800, 7200, 43200 seconds. `hasAttemptsLeft()`, `backoff()`. |
| `Sequence` | `account_id` is a **string** column defaulting to `Sequence::GLOBAL` (`'global'`), not a nullable uuid — same NULL-in-a-unique-index reasoning as `period`. `next_value` is what the next call hands out, not the last one given. Never write it through Eloquent: handing out a number is a locked read plus a write. |
| `Language` | Unique `code`. Scopes `enabled()`, `ordered()`. `native_name` is what the picker shows. |
| `SocialAccount` | `token`/`refresh_token` cast `encrypted` and hidden. `UNIQUE (provider, provider_id)` is the whole safety story: one external identity cannot become two users. |
| `EmailSuppression` | Email lower-cased by a mutator and unique. Reasons: `BOUNCE`, `COMPLAINT`, `MANUAL`, `UNSUBSCRIBE`. |
| `WaitlistSignup` | Email lower-cased and unique. Scope `waiting()` (`invited_at` null). |

---

## Swapping a model

Five, under `config('base-tenant.models.*')`, each with an env override:

```php
'user' => BASE_TENANT_USER_MODEL          'account' => BASE_TENANT_ACCOUNT_MODEL
'role' => BASE_TENANT_ROLE_MODEL          'permission' => BASE_TENANT_PERMISSION_MODEL
'user_invite' => BASE_TENANT_USER_INVITE_MODEL
```

Subclass the package model, do not reimplement it. Every relation inside the
package already resolves through this config.

**Trap:** `model_has_roles` and `model_has_permissions` store the *morph class*
of the user model. If the host swapped it, querying `Base\Tenant\Models\User`
returns an empty roles list in every real install — and no test in the package
suite catches it, because there the configured model *is* the package model.

---

## Anti-patterns

```php
// ✗ Empty roles wherever the host swapped the user model.
User::query()->with('roles')->get();

// ✓
$model = config('base-tenant.models.user', User::class);
$model::query()->with('roles')->get();
```

```php
// ✗ Stores "1" and reads back the string "1": the default type is 'string'.
$account->setSetting('require_deposit', true);
if ($account->getSetting('require_deposit')) { ... }   // also true for "0"

// ✓
$account->setSetting('require_deposit', true, 'boolean');
```

```php
// ✗ Read-modify-write. Two requests at once lose one movement, and two
//   callers get the same number.
$counter = UsageCounter::firstOrCreate([...]);
$counter->increment('value');
$sequence->update(['next_value' => $sequence->next_value + 1]);

// ✓
Meter::increment('storage.bytes', $size);
Sequence::next('invoice');
```

```php
// ✗ Those columns do not exist; the query silently matches nothing or throws.
ActivityLog::whereNotNull('new_values')->get();

// ✓
ActivityLog::forSubject($booking)->get()->map->changed_fields;
```
