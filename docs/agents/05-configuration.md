# Configuration

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-18

## When to use this

Looking up what a key in `config/base-tenant.php` does, what its default is,
which environment variable moves it, or what a default role may do. Also when
you are about to add a config key of your own and want the shape the file
already uses.

## When NOT to use this

Reading configuration at runtime to make a decision the package already makes
for you. Plan limits go through `Feature`, the module switches through
`Module`, the language catalogue through `Language`. Config is the input to
those services, not an API for application code.

---

## Top-level keys, in file order

| Key | What it does | Default |
|---|---|---|
| `multi_team` | Users may belong to more than one account | `false` |
| `installation_state` | How far the project has moved from package to owned code: `fresh`, `installed`, `scaffolded`, `ejected`. `scaffolded` and `ejected` make the provider stand down | `'installed'` |
| `admin.name` / `.email` / `.password` | The staff user `AdminUserSeeder` creates | `Administrator`, `admin@example.com`, `secret123` |
| `tenancy.resolvers` | Ordered list; the first to return an account wins | `Domain`, `ApiToken`, `Session`, `User` resolvers |
| `tenancy.central_domains` | Domains that carry no tenant, comma-separated in env | `[]` |
| `tenancy.on_missing_tenant` | `auto` (unfiltered in console/queue, empty over HTTP), `allow`, `deny` | `'auto'` |
| `tenancy.propagate_to_queue` | Carry the account into queued jobs | `true` |
| `home_url` | Route name to land on after login | `'base-tenant.dashboard'` |
| `subscription` | Stripe checkout: `enabled`, `default_product`, `default_price`, `success_url`, `cancel_url`, `trial_days` | enabled, product/price `null`, trial `14` |
| `plans` | Plan catalogue: `name`, `stripe_price_id`, `features`. Numeric `-1` unlimited, `0` off; booleans gate | `free`, `starter`, `professional` |
| `notifications` | `enabled`, `channels`, `polling_interval`, `dropdown_limit`, `per_page`, `categories`, `ai_usage_threshold`, `quota_warning_percent` | `['database']`, `30`s, `10`, `25`, `50`, `80` |
| `activity_log` | `enabled` and `retention_days` for the audit trail | `true`, `90` |
| `permissions_guard` | Guard the synced permissions and roles belong to | `'web'` |
| `permissions` | The whole permission catalogue, grouped for the role editor | 13 groups, see below |
| `roles` | `system`, `customer` and your own `custom` roles | see Default roles |
| `models` | Swap in subclasses of `user`, `account`, `role`, `permission`, `user_invite` | the package models |
| `layouts.app` / `.guest` | Blade layouts the package's Livewire pages render into | `base-tenant::layouts.app` / `.guest` |
| `menu` | `enabled`, `cache.enabled`, `cache.ttl`, `default_menus` | `true`, `true`, `3600`, `['main','settings']` |
| `routes` | `enabled`, `prefix`, `middleware`, `auth_middleware` | `true`, `''`, `['web']`, `['web','auth','verified','base-tenant.subscription']` |
| `ui.brand_name` / `.brand_logo` | Chrome | `APP_NAME` (`Laravel`), `null` |
| `force_password_change` | `enabled`, `send_welcome_email` for users an admin creates | `false`, `true` |
| `invitations` | `enabled`, `expires_in_days` | `true`, `7` |
| `settings.enabled` / `.schemas` | Typed settings classes shown in the editor; more may be added with `Settings::register()` | `true`, `[]` |

Everything below `settings` is a module section — same shape, own switch.

---

## Module sections

Twelve modules, each owning one top-level key with its own `enabled` flag so
the switch sits next to the settings it governs. A disabled module registers
nothing: no routes, no menu entries, no scheduled tasks, and its facade throws
instead of querying a table that may never have been migrated. See
`Base\Tenant\Support\Module`.

| Key | Module | `enabled` default |
|---|---|---|
| `metering` | M1 usage metering and plan limits | `true` |
| `files` | M2 file storage | `true` |
| `transfer` | M3 imports and exports | `true` |
| `connections` | M5 per-account third-party credentials | `true` |
| `webhooks` | M5 signed outbound webhooks | `true` |
| `languages` | Q1 runtime language catalogue | `true` |
| `social` | Q2 social login | `true` |
| `sequences` | Q3 per-account correlative numbering | `true` |
| `onboarding` | Q4 setup checklist | `true` |
| `suppressions` | Q5 email suppression list | `true` |
| `gdpr` | Q6 export, purge, terms re-acceptance | `true` |
| `presale` | Q7 pre-sale mode | `false` |

`presale` is the one module that stays off: switching it on closes standard
registration, which nobody should get by upgrading a package.

### What each module carries besides `enabled`

| Key | Setting | Default |
|---|---|---|
| `metering` | `metrics` — every metric the product may record, keyed by name, with `type` (`counter`/`gauge`), `reset`, `feature`, `scale`, `stripe_meter`. An undeclared key is refused | `storage.bytes` (gauge, `max_storage_gb`, scale `1073741824`) |
| `files` | `driver` (`vapor` direct-to-S3, or `local`), `disk`, `collections` (`accepts`, `max_size`, `single`, `variants`) | `vapor`, `s3`, one `library` collection: any MIME, 100 MB, `thumb` 200×200 cover + `preview` 1200 contain |
| `transfer` | `retention_days`, `imports`, `exports` — handler classes keyed by the name that appears in a URL | `30`, `[]`, `[]` |
| `connections` | `connectors` — connector classes keyed by name | `[]` |
| `webhooks` | nothing but the switch | — |
| `languages` | `reference` locale, `langsyncer.{url,key,project,webhook_secret}`, `seed` | `en`; LangSyncer at `https://langsyncer.com` with no credentials; seeds `en` (default), `es`, `ca` (disabled) |
| `social` | `allowed_domains` — restrict sign-up to these email domains. A provider appears when its credentials exist in `config/services.php`; there is no second switch | `[]` |
| `sequences` | nothing but the switch | — |
| `onboarding` | `steps` — `label`, `description`, `route`, `completed` class per step. A step with no `completed` class is never marked done | `complete_profile`, `invite_team` |
| `suppressions` | `mailgun_signing_key`, `drivers` reached at `POST /webhooks/suppressions/{driver}` | no key, `mailgun => MailgunDriver` |
| `gdpr` | `retention_days` before a soft-deleted record is destroyed, `terms_version` (empty switches the re-acceptance middleware off), `terms_url`, `exporters` | `30`, `''`, `null`, `ProfileExporter` + `ActivityExporter` |
| `presale` | `seats`, `price_id`, `plan_after` | `50`, `null`, `professional` |

The `seed` list under `languages` only seeds a fresh install. After that the
`languages` table is the source of truth, not the array.

---

## Environment variables

Every variable the config file reads, in file order.

| Variable | Default | Affects |
|---|---|---|
| `BASE_TENANT_MULTI_TEAM` | `false` | `multi_team` |
| `BASE_TENANT_ADMIN_NAME` | `Administrator` | seeded staff user |
| `BASE_TENANT_ADMIN_EMAIL` | `admin@example.com` | seeded staff user |
| `BASE_TENANT_ADMIN_PASSWORD` | `secret123` | seeded staff user |
| `BASE_TENANT_CENTRAL_DOMAINS` | `''` | comma-separated `tenancy.central_domains` |
| `BASE_TENANT_ON_MISSING_TENANT` | `auto` | what tenant-scoped queries do with no account |
| `BASE_TENANT_PROPAGATE_TO_QUEUE` | `true` | tenancy inside queued jobs |
| `BASE_TENANT_HOME_URL` | `base-tenant.dashboard` | post-login route |
| `BASE_TENANT_SUBSCRIPTION_ENABLED` | `true` | subscription system and its routes |
| `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT` | `null` | Stripe product for checkout |
| `BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE` | `null` | Stripe price for checkout |
| `BASE_TENANT_SUBSCRIPTION_SUCCESS_URL` | `base-tenant.checkout.success` | checkout return route |
| `BASE_TENANT_SUBSCRIPTION_CANCEL_URL` | `base-tenant.checkout.cancel` | checkout return route |
| `BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS` | `14` | trial length, cast to int |
| `STRIPE_STARTER_PRICE_ID` | `null` | `plans.starter.stripe_price_id` |
| `STRIPE_PROFESSIONAL_PRICE_ID` | `null` | `plans.professional.stripe_price_id` |
| `BASE_TENANT_NOTIFICATIONS_ENABLED` | `true` | notification system |
| `BASE_TENANT_NOTIFICATIONS_POLLING_INTERVAL` | `30` | bell poll, seconds |
| `BASE_TENANT_ACTIVITY_LOG_ENABLED` | `true` | audit trail |
| `BASE_TENANT_ACTIVITY_LOG_RETENTION` | `90` | days kept by `prune-activity-log` |
| `BASE_TENANT_PERMISSIONS_GUARD` | `web` | guard for synced permissions and roles |
| `BASE_TENANT_USER_MODEL` | `Base\Tenant\Models\User` | model override |
| `BASE_TENANT_ACCOUNT_MODEL` | `Base\Tenant\Models\Account` | model override |
| `BASE_TENANT_ROLE_MODEL` | `Base\Tenant\Models\Role` | model override |
| `BASE_TENANT_PERMISSION_MODEL` | `Base\Tenant\Models\Permission` | model override |
| `BASE_TENANT_USER_INVITE_MODEL` | `Base\Tenant\Models\UserInvite` | model override |
| `BASE_TENANT_LAYOUT_APP` | `base-tenant::layouts.app` | layout for authenticated pages |
| `BASE_TENANT_LAYOUT_GUEST` | `base-tenant::layouts.guest` | layout for guest pages |
| `BASE_TENANT_MENU_ENABLED` | `true` | navigation menus |
| `BASE_TENANT_MENU_CACHE` | `true` | cache resolved trees |
| `BASE_TENANT_MENU_CACHE_TTL` | `3600` | seconds, cast to int |
| `BASE_TENANT_ROUTES_ENABLED` | `true` | whether the package registers routes |
| `BASE_TENANT_ROUTES_PREFIX` | `''` | URL prefix for package routes |
| `APP_NAME` | `Laravel` | `ui.brand_name` |
| `BASE_TENANT_BRAND_LOGO` | `null` | `ui.brand_logo` |
| `BASE_TENANT_FORCE_PASSWORD_CHANGE` | `false` | change password on first login |
| `BASE_TENANT_SEND_WELCOME_EMAIL` | `true` | mail temporary credentials |
| `BASE_TENANT_INVITATIONS_ENABLED` | `true` | invitation system |
| `BASE_TENANT_INVITATIONS_EXPIRES` | `7` | invite lifetime in days |
| `BASE_TENANT_SETTINGS_ENABLED` | `true` | settings system |
| `BASE_TENANT_METERING_ENABLED` | `true` | M1 |
| `BASE_TENANT_FILES_ENABLED` | `true` | M2 |
| `BASE_TENANT_FILES_DRIVER` | `vapor` | upload flow: `vapor` or `local` |
| `BASE_TENANT_FILES_DISK` | `s3` | filesystem disk |
| `BASE_TENANT_TRANSFER_ENABLED` | `true` | M3 |
| `BASE_TENANT_TRANSFER_RETENTION_DAYS` | `30` | days generated files are kept |
| `BASE_TENANT_CONNECTIONS_ENABLED` | `true` | M5 credentials |
| `BASE_TENANT_WEBHOOKS_ENABLED` | `true` | M5 outbound webhooks |
| `BASE_TENANT_LANGUAGES_ENABLED` | `true` | Q1 |
| `BASE_TENANT_LANGUAGES_REFERENCE` | `en` | locale coverage is measured against |
| `LANGSYNCER_URL` | `https://langsyncer.com` | translation service endpoint |
| `LANGSYNCER_API_KEY` | `null` | absent switches LangSyncer off |
| `LANGSYNCER_PROJECT` | `null` | LangSyncer project |
| `LANGSYNCER_WEBHOOK_SECRET` | `null` | signature on `POST /webhooks/langsyncer` |
| `BASE_TENANT_SOCIAL_ENABLED` | `true` | Q2 |
| `BASE_TENANT_SEQUENCES_ENABLED` | `true` | Q3 |
| `BASE_TENANT_ONBOARDING_ENABLED` | `true` | Q4 |
| `BASE_TENANT_SUPPRESSIONS_ENABLED` | `true` | Q5 |
| `MAILGUN_WEBHOOK_SIGNING_KEY` | `null` | verifies Mailgun suppression webhooks |
| `BASE_TENANT_GDPR_ENABLED` | `true` | Q6 |
| `BASE_TENANT_GDPR_RETENTION_DAYS` | `30` | grace period before hard delete |
| `BASE_TENANT_TERMS_VERSION` | `''` | bump to ask everyone again; empty disables the middleware |
| `BASE_TENANT_TERMS_URL` | `null` | where the terms live |
| `BASE_TENANT_PRESALE` | `false` | Q7 |
| `BASE_TENANT_PRESALE_SEATS` | `50` | founding places, cast to int |
| `BASE_TENANT_PRESALE_PRICE_ID` | `null` | Stripe price for the founding plan |

Stripe's own keys (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) are
read by Cashier, not by this file. `installation_state` has no variable on
purpose: the commands write it.

---

## The permission catalogue

`config('base-tenant.permissions')`, grouped. This list is what `'*'` and
`'group.*'` expand against.

| Group | Permissions |
|---|---|
| `users` | `view`, `create`, `update`, `delete`, `impersonate` |
| `roles` | `view`, `create`, `update`, `delete` |
| `accounts` | `view`, `create`, `update`, `delete`, `billing` |
| `invitations` | `view`, `create`, `revoke` |
| `activity` | `view` |
| `settings` | `view`, `update` |
| `menus` | `view`, `update` |
| `features` | `view`, `update` |
| `usage` | `view` |
| `files` | `view`, `upload`, `delete` |
| `languages` | `manage` — the catalogue belongs to the installation, not to a customer |
| `transfers` | `view`, `import`, `export` |
| `connections` | `connections.manage`, `webhooks.manage` |

Labels come from the `base-tenant::permissions` translation file, keyed by
permission name.

---

## Default roles

Roles declared in config are global: every account can assign them. Accounts
may define their own on top through the role editor. `system` and `customer`
and `custom` are all synced the same way; the split is documentation.

### System

| Key | Name | Permissions |
|---|---|---|
| `administrator` | Administrator | `*` — the whole catalogue |
| `administrator-finance` | Administrator Finance | `accounts.view`, `accounts.billing`, `users.view`, `activity.view`, `usage.view` |
| `administrator-tech` | Administrator Tech | `users.*`, `roles.*`, `accounts.view`, `accounts.update`, `activity.view`, `settings.*`, `menus.*`, `features.*`, `files.*`, `languages.manage`, `transfers.*`, `connections.manage`, `webhooks.manage` |

`administrator-tech` deliberately has no `accounts.create`, no
`accounts.delete` and no `accounts.billing`.

### Customer

| Key | Name | Permissions |
|---|---|---|
| `customer-admin` | Customer Admin | `users.*`, `roles.*`, `invitations.*`, `accounts.view`, `accounts.update`, `accounts.billing`, `activity.view`, `settings.*`, `menus.*`, `features.view`, `usage.view`, `files.*`, `transfers.*`, `connections.manage`, `webhooks.manage` |
| `customer-user` | Customer User | `users.view`, `accounts.view`, `settings.view`, `menus.view`, `features.view`, `files.view`, `files.upload` |
| `customer-viewer` | Customer Viewer | `accounts.view`, `menus.view` |
| `customer-finance` | Customer Finance | `accounts.view`, `accounts.billing`, `users.view`, `activity.view`, `usage.view` |

`roles.custom` ships empty, with a commented example. Your own roles go there,
with the same four keys: `key`, `name`, `is_system`, `permissions`.

### Expansion happens at sync time

`'*'` and `'users.*'` are resolved by `PermissionRegistry::sync()` against the
permissions declared **at that moment**, and the result is written to the
`role_has_permissions` table. Nothing re-expands them later.

```bash
php artisan k2labs-base:sync-roles          # after every change to permissions or roles
php artisan k2labs-base:sync-roles --show   # list the resulting roles and their counts
```

**So adding a permission group without re-running the command leaves even the
`administrator` role without it**, and the failure is silent: the screen just
403s for everyone. Resolution also intersects with the catalogue, so a role
granting a permission that is not declared gets nothing for that entry.

---

## Outside production

One behaviour changes, and only one. When `app()->environment()` is anything
other than `production` **and** Stripe is not fully configured — any of
`cashier.key`, `cashier.secret`, `subscription.default_product`,
`subscription.default_price` missing — `HasSubscription` lets the request
through and `DoesNotHaveSubscription` redirects to `home_url`. A local
database is usable without a Stripe account.

Two things this is not: it is not limited to `local` and `testing`, it covers
every non-production environment; and it is not a general bypass — users with
`is_admin` skip the subscription check in production too. `GET /checkout`
still aborts `503` when `default_product` or `default_price` is missing, which
is what you hit if Stripe keys are present but the product is not.

---

## Anti-patterns

```php
// ✗ Reads the plan table directly: ignores per-account overrides, trials and
//   the account actually in context.
$max = config('base-tenant.plans.free.features.max_users');

// ✓
Feature::limit('max_users');
Feature::withinLimit('max_users', $count);
```

```php
// ✗ installation_state is written by scaffold and eject, which refuse to run
//   out of order. Editing it by hand puts the provider in a state the code
//   never produced — routes stood down with nothing having replaced them.
'installation_state' => 'scaffolded',

// ✓
php artisan k2labs-base:scaffold
```

```php
// ✗ A new group added to config and never synced. The permission does not
//   exist in the database, so 'bookings.*' resolved to nothing and every role
//   silently lacks it.
'permissions' => ['bookings' => ['bookings.view', 'bookings.update']],

// ✓ Same edit, followed by the command that makes it real.
php artisan k2labs-base:sync-roles
```

```php
// ✗ A module switch checked by hand, one call site at a time, and forgotten
//   at the next one.
if (config('base-tenant.files.enabled')) { ... }

// ✓ The facade already refuses when its module is off, and the provider
//   registers nothing for a disabled module.
Module::enabled(Module::FILES);
```
