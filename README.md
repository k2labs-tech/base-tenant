# base/tenant

A multi-tenant SaaS foundation for Laravel 13. Tenancy that survives queued
jobs, permissions that are per account, plan limits that are actually enforced,
and fourteen capability modules on top — domains, per-tenant security policies,
files, metering, imports, connections, webhooks, languages and more.

Built for B2B products where every customer is an account and nothing may ever
leak between them.

> **Proprietary.** See [`docs/LICENSE.md`](docs/LICENSE.md). The distribution
> channel is not settled yet — see [Getting it](#getting-it).

**Requires** PHP 8.4 · Laravel 13 · Livewire 4 · Flux UI 2.4 (free tier is
enough) · spatie/laravel-permission 8 · MySQL or PostgreSQL

## What it does

Everything below is built, tested and documented. Each module has a switch, so a
product that does not want files or webhooks does not carry their tables.

### The core

| | |
|---|---|
| **Tenancy** | One active account per request (`Tenant::current()`), resolved from domain, API token, session or user, and carried into queued jobs, cache keys and broadcast channels |
| **Isolation** | `BelongsToAccount` adds a global scope and stamps new records, so a forgotten `where` cannot leak another customer's data |
| **Permissions** | spatie/laravel-permission with `account_id` as the team key. Roles resolve per account, from the database, in requests, jobs, commands and API calls alike |
| **Per-account roles** | A shared catalogue plus roles each tenant defines for itself, edited through a role × permission matrix |
| **Policies** | `User`, `Account`, `Role` and `UserInvite`, replacing scattered role checks |
| **Navigation** | Declared in code, stored in the database, reorderable and hideable per account, filtered by permission and feature, with live badges |
| **Feature flags** | Plan features with per-account overrides and expiry — `Feature::for($account)->active('export')` |
| **Typed settings** | Schema classes with declared defaults over a key-value store, rendered as a form from the property types |
| **Auth** | Login, registration, password reset, email verification, 2FA with recovery codes, audited impersonation, forced password change |
| **Billing** | Laravel Cashier with plan-based feature gates |
| **Activity** | Account-scoped audit trail with automatic model change tracking and sensitive-field filtering |
| **Notifications** | Database notifications with a bell component, polling and a daily digest |
| **Invitations** | Token-based, with email and role assignment |

### The modules

| Module | Facade / entry point | What it gives you |
|---|---|---|
| **Security policies** | `Security` | Per account: enforced 2FA with a grace period, allowed email domains, an IP allowlist with a warn-first mode, and session timeout |
| **Domains** | `Domain` | A subdomain per customer, and domains of their own served only once a TXT record proves they control them |
| **Usage metering** | `Meter` | Atomic counters and gauges per account, plan limits enforced under a lock, 402 with an upgrade call to action, warnings at 80% and 100%, hourly reporting to Stripe Billing Meters |
| **Files** | `HasFiles`, `FileStore` | Direct-to-S3 uploads that never pass through PHP, collections with type and size rules, image variants, per-account quota, a media library screen |
| **Imports and exports** | `Transfer` | CSV in and out, automatic column mapping, chunked queued jobs, rejected rows returned as a file to correct and re-upload |
| **Module generator** | `k2labs-base:make-module` | Model, migration, factory, policy, both Livewire components, both views, both locales, tests and an agent doc, from one command |
| **Connections** | `Connection` | A customer's credentials for third parties, encrypted at rest, with nightly health checks and notification on failure |
| **Outbound webhooks** | `Webhook` | Signed deliveries with retries at 1m/5m/30m/2h/12h and automatic disabling of dead endpoints |
| **Languages** | `Language` | Locales enabled and disabled at runtime, without a deploy, with per-locale translation coverage |
| **Social login** | — | Google, LinkedIn and Microsoft Entra ID, with an anti-takeover rule and 2FA respected |
| **Sequences** | `Sequence` | Correlative numbering per account, locked, with automatic period resets and formats |
| **Onboarding** | `Onboarding` | A declarative setup checklist that disappears when it is done |
| **Email suppressions** | `Suppression` | A global send guard fed by signed provider webhooks |
| **GDPR** | — | Personal data export, scheduled purge of expired soft deletes, versioned terms acceptance |
| **Pre-sale** | `Presale` | Closed registration, landing page, waiting list, founding seats with a live counter |

### And around all of it

- **Livewire 4 + Flux UI** — every screen on one table pattern: search, sort, density and page size in the URL, sticky headers, designed empty states, loading skeletons
- **Documentation for AI agents** — `docs/agents/`, one file per capability with a capability map, publishable into the host application
- **Multi-tenant test kit** — `assertTenantIsolated`, `assertJobCarriesTenant`, `assertPermissionIsAccountScoped`
- **832 tests** covering the package itself

## Getting it

**The distribution channel is not decided yet.** Until it is, the package is
consumed either from a private VCS repository or from a local path checkout:

```json
"repositories": {
    "base/tenant": { "type": "path", "url": "../base-tenant" }
}
```

Then:

```bash
composer require base/tenant:*
php artisan k2labs-base:install
```

The installer asks how you want the database configured, whether you want
multi-team mode, Stripe and a test user, tests the database connection before
writing anything, and offers to publish the AI agent documentation into the
project. `--no-interaction` takes the defaults for CI.

Manual setup, and what the installer actually changes, are in
[`docs/INSTALLATION.md`](docs/INSTALLATION.md).

The fastest way to see it working is not to install it into an existing
application but to start a new project from the starter kit, which arrives with
this already installed and configured.

## First run

```bash
php artisan serve
```

Two users exist after seeding, and they see different things:

| | | |
|---|---|---|
| `admin@example.com` | `secret123` | Platform staff. Belongs to no account, passes every permission check, and uses the account switcher to enter a customer's account |
| `test@example.com` | `password` | A customer administrator inside "Test Company" |

Both are configurable — `BASE_TENANT_ADMIN_EMAIL` and
`BASE_TENANT_ADMIN_PASSWORD` — and the test user is optional at install time.
Change them before anything reaches a network.

## Configuration

```bash
php artisan vendor:publish --tag=base-tenant-config
```

Every key, every environment variable and the default roles are tabulated in
[`docs/agents/05-configuration.md`](docs/agents/05-configuration.md). The four
that decide the shape of an installation:

| | |
|---|---|
| `BASE_TENANT_MULTI_TEAM` | One account per user, or several |
| `BASE_TENANT_ON_MISSING_TENANT` | What a scoped query does with no account in context: `auto`, `allow` or `deny` |
| `BASE_TENANT_SUBSCRIPTION_ENABLED` | Whether billing and plan gates are in play |
| `BASE_TENANT_*_ENABLED` | One per module — off means no routes, no menu entries, no tables |

## Single-team or multi-team

`multi_team` decides whether a user belongs to one account or several. It is the
one architectural choice to make before writing code, because moving between
them later means migrating the pivot and every place that assumes a single
account.

- **Single-team** (default) — a user belongs to one account, held on
  `users.account_id`. Simpler queries, no switcher, no ambiguity about which
  account a request is about. Right for most B2B products.
- **Multi-team** — a user belongs to several through the `account_user` pivot
  and switches between them. Right when the same person genuinely works across
  customers: agencies, consultancies, franchise groups.

Either way roles are held *within* an account, so the same person can be an
administrator in one and a viewer in another.

The trade-offs, the schema and the switching are in
[the manual](docs/USAGE.md#multi-team-versus-single-team).

## Usage

The manual is [`docs/USAGE.md`](docs/USAGE.md). The shape of it:

```php
use Base\Tenant\Traits\BelongsToAccount;

class Invoice extends Model
{
    use BelongsToAccount;   // scoped on read, stamped on create, in jobs too
}
```

```php
Invoice::all();                        // this account's invoices
Invoice::query()->acrossAccounts();    // every account — superadmin tooling only
Tenant::runFor($other, fn () => ...);  // as another tenant, restored afterwards
```

That trait is the whole integration for a tenant-owned model. Permissions,
menus, settings and feature gates are declared in config and read through
facades; none of them needs a column on your model.

### The module APIs at a glance

Full detail, and the reasoning behind each one, in [the manual](docs/USAGE.md).

```php
use Base\Tenant\Facades\{Meter, Transfer, Connection, Webhook, Language, Sequence, Onboarding, Suppression, Presale};

// Usage metering — counters, gauges and plan limits
Meter::increment('documents.generated');
Meter::incrementOrFail('documents.generated');   // 402 when the plan is full
Meter::remaining('storage.bytes');

// Files — the bytes never pass through PHP
$property->filesIn('photos');
$property->firstFile('cover')?->variantUrl('thumb');

// Imports and exports — chunked, queued, with an error file to correct
Transfer::import('guests', $uploadedFile, $mapping);
Transfer::export('guests');

// Third-party credentials, encrypted, health-checked nightly
Connection::client('wubook');
Connection::store('wubook', ['token' => '...']);

// Signed outbound webhooks with retries
Webhook::dispatch('booking.confirmed', ['id' => $booking->id]);

// Languages on and off without a deploy
Language::enable('ca');
Language::codes();

// Correlative numbering that does not repeat or skip
Sequence::next('invoices', format: 'F{year}-{number:5}', period: 'year');

// The setup checklist
Onboarding::progress();

// The global send guard
Suppression::isSuppressed($email);

// Pre-sale and the waiting list
Presale::seatsLeft();
Presale::join($email);
```

Components you drop into a view:

```blade
<livewire:base-tenant.files.uploader :fileable="$property" collection="photos" />
<livewire:base-tenant.files.gallery :fileable="$property" collection="photos" />
<livewire:base-tenant.files.usage-badge />
<livewire:base-tenant.onboarding.checklist />
<livewire:base-tenant.presale.pricing-table />
<livewire:base-tenant.presale.waitlist-form source="landing" />
<livewire:base-tenant.profile.connected-accounts />
<x-base-tenant::social-buttons />
```

## Routes

The package registers the following routes:

- `/login`, `/register`, `/logout` - Authentication
- `/dashboard` - Main dashboard
- `/profile` - User profile
- `/users` - User management
- `/invitations` - Pending invitations
- `/accounts` - Account management
- `/roles` - Role and permission editor
- `/navigation` - Per-account menu editor
- `/features` - Feature flag editor, plan baseline and per-account overrides
- `/domains` - Subdomain and custom domains
- `/security` - Per-tenant security policy
- `/settings` - Typed settings editor
- `/activity` - Audit trail
- `/checkout` - Subscription checkout
- `/billing` - Stripe billing portal

## Artisan Commands

Every command lives under `k2labs-base:`. The v1 `base-tenant:*` names still
resolve, as deprecated aliases, and are removed in v3.

```bash
# Core
php artisan k2labs-base:install                 # guided installation
php artisan k2labs-base:sync-roles [--show]     # permissions and global roles from config
php artisan k2labs-base:sync-menus              # menus declared in code
php artisan k2labs-base:prune-activity-log      # drop old audit entries        (daily)

# Scaffolding
php artisan k2labs-base:scaffold                # copy the package code into your app
php artisan k2labs-base:eject                   # remove the package, keep the code
php artisan k2labs-base:make-module Booking --fields="..."   # a whole CRUD module

# Metering and files
php artisan k2labs-base:report-usage            # metered usage to Stripe        (hourly)
php artisan k2labs-base:reconcile-storage       # recount the storage gauge      (weekly)

# Languages
php artisan k2labs-base:lang-status [--missing] [--fail-under=90]
php artisan k2labs-base:lang-push                # source keys to LangSyncer
php artisan k2labs-base:lang-pull [locale] [--enable]

# Domains
php artisan k2labs-base:verify-domains [--domain=] [--all]   # re-check DNS      (daily)

# Connections, mail and privacy
php artisan k2labs-base:check-connections        # health-check credentials      (daily)
php artisan k2labs-base:import-suppressions bounces.csv
php artisan k2labs-base:export-user-data ada@example.test [--path=]
php artisan k2labs-base:purge-deleted [--days=30] [--dry-run]                    (daily)

# Launch
php artisan k2labs-base:presale-open [--batch=50] [--dry-run]

# Documentation
php artisan k2labs-base:publish-agent-docs       # docs/agents/ + CLAUDE.md stub
```

## Module switches

Each capability is a module. Off means no routes, no menu entries, no scheduled
work, and a facade that throws rather than querying tables you never migrated.

```
BASE_TENANT_DOMAINS_ENABLED        BASE_TENANT_SECURITY_ENABLED
BASE_TENANT_METERING_ENABLED       BASE_TENANT_FILES_ENABLED
BASE_TENANT_TRANSFER_ENABLED       BASE_TENANT_CONNECTIONS_ENABLED
BASE_TENANT_WEBHOOKS_ENABLED       BASE_TENANT_LANGUAGES_ENABLED
BASE_TENANT_SOCIAL_ENABLED         BASE_TENANT_SEQUENCES_ENABLED
BASE_TENANT_ONBOARDING_ENABLED     BASE_TENANT_SUPPRESSIONS_ENABLED
BASE_TENANT_GDPR_ENABLED           BASE_TENANT_PRESALE
```

All default to on except `BASE_TENANT_PRESALE`, which closes standard
registration and is therefore not something to inherit from an upgrade.

## Documentation

| | |
|---|---|
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installing, configuring and seeding, step by step |
| [`docs/USAGE.md`](docs/USAGE.md) | **The manual.** Every capability with copyable code |
| [`docs/agents/`](docs/agents/00-index.md) | One file per capability for AI agents, with a capability map |
| [`docs/FRONTEND.md`](docs/FRONTEND.md) | The theme, dark mode and how the host builds its assets |
| [`docs/NOTIFICATIONS.md`](docs/NOTIFICATIONS.md) | Channels, categories and the daily digest |
| [`docs/SCAFFOLD-EJECT.md`](docs/SCAFFOLD-EJECT.md) | Taking ownership of the code |
| [`docs/UPGRADE.md`](docs/UPGRADE.md) | Moving between versions |
| [`docs/CHANGELOG.md`](docs/CHANGELOG.md) | What changed and when |

### For AI agents

`docs/agents/` is written for them rather than for people: one capability per
file, short enough to sit in a context window next to the code of the task, and
starting with a capability map that answers "does this already exist?" before
anything gets written.

```bash
php artisan k2labs-base:publish-agent-docs
```

Copies it into the application and maintains a section of `CLAUDE.md` /
`AGENTS.md` between markers, so it can be re-run after every `composer update`
without touching what the project wrote around it.

## Starter kit, dependency, or your own code

Use the package as a dependency and keep receiving updates, or copy the code into
your application and remove the package entirely. Moving between the two is a
command, not a rewrite.

```bash
php artisan k2labs-base:scaffold --dry-run   # see exactly what would move
php artisan k2labs-base:scaffold             # ~200 files into app/, resources/, routes/
composer dump-autoload

# run your suite against the copied code, read the diff, then:
php artisan k2labs-base:eject
```

Scaffolding rewrites `Base\Tenant\` to `App\`, `<x-base-tenant::*>` to
`<x-tenant.*>` and `base-tenant::` view and translation keys to `tenant::`, and
generates `app/Providers/TenancyServiceProvider.php` with every registration the
package's own provider performed. While scaffolded, the package stands down so
nothing is registered twice — you can still go back by setting
`installation_state` to `installed`.

Full details in [docs/SCAFFOLD-EJECT.md](docs/SCAFFOLD-EJECT.md).

## Testing

Run the package tests:

```bash
composer test
```

### Testing your own tenancy

The package ships assertions your application can run against its own models:

```php
use Base\Tenant\Tests\TenancyAssertions;

class InvoiceTenancyTest extends TestCase
{
    use TenancyAssertions;

    public function test_invoices_are_isolated(): void
    {
        $this->assertTenantIsolated(
            Invoice::class,
            fn (Account $account) => Invoice::factory()->create()
        );
    }

    public function test_the_sync_job_carries_the_tenant(): void
    {
        $this->assertJobCarriesTenant(new SyncInvoicesJob);
    }
}
```

## Licence

Proprietary. See [`docs/LICENSE.md`](docs/LICENSE.md).

> Before this repository is published: the licence file and `composer.json`
> still carry placeholder holder and author details, and `homepage` is unset.
