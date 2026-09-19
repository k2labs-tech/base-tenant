# base/tenant

A multi-tenant SaaS foundation for Laravel 13. Tenancy that survives queued
jobs, permissions that are per account, plan limits that are actually enforced,
and fifteen capability modules on top — domains, per-tenant security policies,
passwordless sign-in, files, metering, imports, webhooks, languages and more.

Built for B2B products where every customer is an account and nothing may ever
leak between them.

> **Proprietary.** See [`docs/LICENSE.md`](docs/LICENSE.md). The distribution
> channel is not settled yet — see [Getting it](#getting-it).

## Contents

- [Requirements](#requirements)
- [What it does](#what-it-does)
- [Getting it](#getting-it)
- [First run](#first-run)
- [How tenancy works](#how-tenancy-works)
- [Configuration](#configuration)
- [Single-team or multi-team](#single-team-or-multi-team)
- [Usage](#usage)
- [Security](#security)
- [Privacy and GDPR](#privacy-and-gdpr)
- [Routes](#routes)
- [Middleware](#middleware)
- [Artisan commands](#artisan-commands)
- [Scheduled work](#scheduled-work)
- [Module switches](#module-switches)
- [Starter kit, dependency, or your own code](#starter-kit-dependency-or-your-own-code)
- [Testing](#testing)
- [Documentation](#documentation)
- [Licence](#licence)

## Requirements

| | |
|---|---|
| PHP | 8.4 |
| Laravel | 13 |
| Livewire | 4 |
| Flux UI | 2.4 — the free tier is enough; Flux Pro is optional and only for your own screens |
| spatie/laravel-permission | 8 |
| Laravel Cashier | 16, when billing is enabled |
| Laravel Socialite | 5, when social login is enabled |
| web-auth/webauthn-lib | 5.3, for passkeys |
| Database | MySQL, MariaDB or PostgreSQL in production; SQLite works for development and tests |

The whole schema is migrated and seeded against MySQL 8.4 as well as SQLite.

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
| **Invitations** | Token-based, with email and role assignment, honouring the account's allowed email domains |

### The modules

| Module | Facade / entry point | What it gives you |
|---|---|---|
| **Security policies** | `Security` | Per account: enforced 2FA with a grace period, allowed email domains, an IP allowlist with a warn-first mode, and session timeout |
| **Active sessions** | `Sessions` | Where each person is signed in, with remote revocation that works on any session driver |
| **Passkeys** | `Passkey` | WebAuthn sign-in bound to the origin, on `web-auth/webauthn-lib`. Not yet exercised against a real authenticator |
| **Magic links** | `MagicLink` | Single-use, short-lived sign-in links that never skip the second factor |
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
| **GDPR** | — | Personal data export, erasure across every table that holds personal data, scheduled purge of expired soft deletes, versioned terms acceptance |
| **Pre-sale** | `Presale` | Closed registration, landing page, waiting list, founding seats with a live counter |

### And around all of it

- **Livewire 4 + Flux UI** — every screen on one table pattern: search, sort, density and page size in the URL, sticky headers, designed empty states, loading skeletons, dark mode throughout
- **Spanish and English** — every string goes through the translator, in both locales
- **Documentation for AI agents** — `docs/agents/`, one file per capability with a capability map, publishable into the host application
- **Multi-tenant test kit** — `assertTenantIsolated`, `assertJobCarriesTenant`, `assertPermissionIsAccountScoped`
- **948 tests** covering the package itself, failing on deprecations, notices and warnings

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

The installer:

1. asks how the database should be configured — sqlite, mysql, mariadb or
   pgsql — and **tests the connection before writing anything**, re-asking with
   the driver's own error on failure;
2. asks whether you want multi-team mode, Stripe and a test user;
3. migrates, syncs permissions, roles and menus, and seeds the administrator;
4. adds Flux's stylesheet and the package theme to `resources/css/app.css`;
5. offers to publish the AI agent documentation into the project.

`--no-interaction` takes the defaults for CI; `--database` forces the database
question when the current connection already works.

Manual setup, and what the installer actually changes, are in
[`docs/INSTALLATION.md`](docs/INSTALLATION.md).

The fastest way to see it working is not to install it into an existing
application but to start a new project from the starter kit, which arrives with
this already installed and configured.

## First run

```bash
npm run build        # or npm run dev
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

`php artisan migrate:fresh --seed` rebuilds a working application: the kit's
`DatabaseSeeder` calls `InitialLoadSeeder` and `AdminUserSeeder`, so
permissions, roles, menus and the administrator come back with the schema.

## How tenancy works

Every request has at most one active account. Resolvers run in order until one
answers:

1. **Domain** — a verified custom domain or a customer subdomain
2. **API token** — the account the token belongs to
3. **Session** — the account the user last switched to
4. **User** — the user's own account

Once resolved, the account follows the work wherever it goes:

- **Queries** — models using `BelongsToAccount` are filtered and stamped
- **Queued jobs** — the account is serialised with the job and restored on the worker
- **Cache** — `Tenant::cacheKey('stats')` prefixes the key with the account
- **Broadcasting** — `Tenant::channel('orders')` names an account-scoped channel
- **Permissions** — the spatie team key is set to the current account

What a scoped query does when there is no account in context is a decision, not
an accident: `BASE_TENANT_ON_MISSING_TENANT` is `auto`, `allow` or `deny`.

```php
Tenant::current();                        // the Account, or null
Tenant::runFor($account, fn () => ...);   // as another tenant, restored afterwards
Tenant::runWithout(fn () => ...);         // explicitly outside any tenant
Tenant::eachAccount(fn ($account) => ...);// every account, in chunks, each in context
```

The architecture, and the reasoning behind each choice, is in
[`docs/agents/01-architecture.md`](docs/agents/01-architecture.md).

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

Routes are mounted under `BASE_TENANT_ROUTES_PREFIX` (empty by default), and the
middleware stacks for public and authenticated routes live in
`base-tenant.routes.middleware` and `base-tenant.routes.auth_middleware`.

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

### Features, settings and navigation

```php
use Base\Tenant\Facades\{Feature, Settings, Menu};

Feature::active('export');                     // plan baseline plus account override
Feature::for($account)->limit('seats');
Feature::withinLimit('seats', $currentCount);

Settings::register(BookingSettings::class);    // a typed schema with defaults
Settings::for($account)->get(BookingSettings::class)->cancellation_hours;

Menu::register('sidebar', fn ($menu) => ...);  // declared in code...
Menu::sync();                                  // ...stored in the database
```

### The module APIs at a glance

Full detail, and the reasoning behind each one, in [the manual](docs/USAGE.md).

```php
use Base\Tenant\Facades\{Meter, Transfer, Connection, Webhook, Language, Sequence, Onboarding, Suppression, Presale, Domain};

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

// A subdomain per customer, and domains of their own
Domain::claimSubdomain($account, 'acme');        // acme.your-app.com
$domain = Domain::addDomain($account, 'app.acme.com');
Domain::verify($domain);                         // checks the TXT record
Domain::urlFor($account);
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

### A new module in one command

```bash
php artisan k2labs-base:make-module Booking --fields="reference:string,guests:integer,starts_at:date,notes:text:nullable"
```

Generates the model with `BelongsToAccount`, migration, factory, policy, the
list and edit Livewire components with their views, Spanish and English
translations, its permissions in the config, tests and an agent document. Then
run the migration and `k2labs-base:sync-roles && k2labs-base:sync-menus`.

## Security

Each account sets its own rules at `/security`. The rules live in the typed
settings (`SecurityPolicySettings`), so adding one is adding a property.

| Rule | Behaviour |
|---|---|
| **Enforced 2FA** | With a grace period that starts once, when the rule is switched on or when someone joins the account — never counted from `created_at` |
| **Allowed email domains** | Applied in `InvitationService`, so it holds for invitations sent from a command or a job, on resend and on acceptance |
| **IP allowlist** | `off`, `warn` (records what would be blocked) or `enforce`. IPv4 and IPv6 CIDR. An empty list never blocks, and the screen refuses to enforce from an address the list does not cover |
| **Session timeout** | Ends sessions idle for longer than the account allows. A `wire:poll` tick does not count as activity |

Every default is the permissive one, so an upgrade never locks anyone out of an
account that did not ask for a change.

```php
use Base\Tenant\Facades\{Security, Sessions, MagicLink};

Security::requiresTwoFactor($account);
Security::allowsEmail('ada@acme.com');
Security::allowsIp($request->ip());

Sessions::forUser($user);          // where they are signed in
Sessions::revokeOthers($user);     // keeps the current one
Sessions::revokeAll($user);

MagicLink::request($email, $request);
```

**Active sessions** are stored in a table of their own rather than Laravel's
`sessions`, so they work with whichever session driver the host uses.
Revocation is a mark the tracking middleware reads: a revoked session ends on
its next request, not the instant the button is pressed. Session identifiers are
stored hashed and never shown.

**Magic links** are single-use and short-lived. Opening the email shows a
button; only the POST spends the token, so a mail scanner prefetching the link
cannot burn it. A magic link never skips the second factor.

**Passkeys** are offered on the login screen and managed from the profile. The
WebAuthn ceremony lives in one script shared by both.

The details, including why the middleware must sit on route groups and not in
the global stack, are in [`docs/agents/16-security.md`](docs/agents/16-security.md)
and [`docs/agents/17-passwordless.md`](docs/agents/17-passwordless.md).

## Privacy and GDPR

| | |
|---|---|
| **Export** | `k2labs-base:export-user-data` gathers everything held about a person through the exporters in `gdpr.exporters` — profile, activity, sessions |
| **Erasure** | The erasers in `gdpr.erasers` run in `forceDeleting` through the `PurgesPersonalData` trait, so every path that destroys a user cleans the same tables — not only the purge command |
| **Purge** | Soft-deleted users past their retention window are destroyed daily |
| **Terms** | Versioned acceptance, enforced by the `base-tenant.terms` middleware |

The erasers shipped cover activity (anonymised), sessions — the package's own
and the framework's — magic links and passkeys, social accounts, notifications,
invitations, files, transfers and memberships.

A module of your own that stores personal data adds an exporter and an eraser:

```php
use Base\Tenant\Gdpr\GdprEraser;

class BookingEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        Booking::query()->acrossAccounts()->where('guest_user_id', $user->id)->delete();
    }
}
```

```php
// config/base-tenant.php
'gdpr' => [
    'erasers' => [
        // ...the package's own
        BookingEraser::class,
    ],
],
```

A domain listed in `exporters` and missing from `erasers` is data the product
discloses and then fails to delete. See [`docs/agents/25-gdpr.md`](docs/agents/25-gdpr.md).

## Routes

Every route is named `base-tenant.*` and mounted under
`BASE_TENANT_ROUTES_PREFIX`. Module routes exist only while their module is on.

**Authentication**

| Path | |
|---|---|
| `/login`, `/register`, `/logout` | Sign in, sign up, sign out |
| `/forgot-password`, `/reset-password/{token}` | Password reset |
| `/two-factor-challenge` | Second factor at sign-in |
| `/verify-email`, `/verify-email/{id}/{hash}` | Email verification |
| `/confirm-password`, `/password/change` | Password confirmation and forced change |
| `/magic-link`, `/magic-link/{token}` | Passwordless sign-in request and consumption |
| `/passkeys/login/options`, `/passkeys/login` | Passkey sign-in ceremony |
| `/passkeys/options`, `/passkeys` | Passkey registration |
| `/auth/{provider}/redirect`, `/auth/{provider}/callback` | Social login |
| `/invitations/accept/{token}` | Invitation acceptance |

**Application**

| Path | |
|---|---|
| `/dashboard`, `/profile`, `/upgrade` | Dashboard, profile (2FA, passkeys, sessions, connected accounts), upgrade prompt |
| `/users`, `/users/create`, `/users/{user}/edit` | User management |
| `/accounts`, `/accounts/create`, `/accounts/{account}/edit` | Account management |
| `/invitations` | Pending invitations |
| `/roles` | Role × permission matrix |
| `/navigation` | Per-account menu editor |
| `/features` | Plan baseline and per-account overrides |
| `/settings` | Typed settings editor |
| `/activity` | Audit trail |
| `/notifications` | Notification centre |
| `/domains` | Subdomain and custom domains |
| `/security` | Per-tenant security policy |
| `/usage` | Metered usage against plan limits |
| `/files` | Media library |
| `/transfers` | Imports and exports |
| `/connections` | Third-party credentials |
| `/languages` | Runtime locales and coverage |
| `/terms` | Terms acceptance |
| `/checkout`, `/billing` | Subscription checkout and the Stripe billing portal |
| `/impersonate/leave` | Ending an impersonation |

**Incoming webhooks**

| Path | |
|---|---|
| `POST /webhooks/suppressions/{driver}` | Bounces and complaints from the mail provider, signed |
| `POST /webhooks/langsyncer` | Translation updates |

## Middleware

| Alias | What it does |
|---|---|
| `base-tenant.account-context` | Resolves and sets the current account |
| `base-tenant.subscription` | Requires an active subscription |
| `base-tenant.no-subscription` | Only for accounts without one — checkout pages |
| `base-tenant.feature:export` | Requires a plan feature |
| `base-tenant.metered:documents.generated` | Refuses with 402 when a usage limit is reached |
| `base-tenant.password-changed` | Sends users with a forced change to `/password/change` |
| `base-tenant.terms` | Requires acceptance of the current terms |
| `base-tenant.locale` | Applies the user's or account's locale |
| `base-tenant.two-factor` | Sends anyone past their 2FA grace period to set it up |
| `base-tenant.ip-allowlist` | Applies the account's IP allowlist |
| `base-tenant.session-timeout` | Ends sessions idle beyond the account's limit |
| `base-tenant.track-session` | Records the active session and ends revoked ones |

The four security middleware are **not applied by default**. Add them to
`base-tenant.routes.auth_middleware` or to your own route groups — not to the
global stack, because Livewire replays only route middleware on its update
requests.

## Artisan commands

Every command lives under `k2labs-base:`. The v1 `base-tenant:*` names still
resolve, as deprecated aliases, and are removed in v3.

```bash
# Core
php artisan k2labs-base:install                 # guided installation
php artisan k2labs-base:sync-roles [--show]     # permissions and global roles from config
php artisan k2labs-base:sync-menus              # menus declared in code
php artisan k2labs-base:prune-activity-log      # drop old audit entries

# Scaffolding
php artisan k2labs-base:scaffold                # copy the package code into your app
php artisan k2labs-base:eject                   # remove the package, keep the code
php artisan k2labs-base:make-module Booking --fields="..."   # a whole CRUD module

# Metering and files
php artisan k2labs-base:report-usage            # metered usage to Stripe
php artisan k2labs-base:reconcile-storage       # recount the storage gauge

# Languages
php artisan k2labs-base:lang-status [--missing] [--fail-under=90]
php artisan k2labs-base:lang-push                # source keys to LangSyncer
php artisan k2labs-base:lang-pull [locale] [--enable]

# Domains and sessions
php artisan k2labs-base:verify-domains [--domain=] [--all]   # re-check DNS
php artisan k2labs-base:prune-sessions [--days=]             # drop old sessions

# Connections, mail and privacy
php artisan k2labs-base:check-connections        # health-check credentials
php artisan k2labs-base:import-suppressions bounces.csv
php artisan k2labs-base:export-user-data ada@example.test [--path=]
php artisan k2labs-base:purge-deleted [--days=30] [--dry-run]

# Launch
php artisan k2labs-base:presale-open [--batch=50] [--dry-run]

# Documentation
php artisan k2labs-base:publish-agent-docs       # docs/agents/ + CLAUDE.md stub
```

## Scheduled work

The package registers its recurring commands on the application's scheduler
itself; the host only needs the usual `schedule:run` cron entry. A module that
is off schedules nothing.

| Command | Frequency |
|---|---|
| `prune-activity-log` | Daily |
| `report-usage` | Hourly |
| `reconcile-storage` | Weekly |
| `check-connections` | Daily |
| `purge-deleted` | Daily |
| `verify-domains` | Daily |
| `prune-sessions` | Daily |

## Module switches

Each capability is a module. Off means no routes, no menu entries, no scheduled
work, and a facade that throws rather than querying tables you never migrated.

```
BASE_TENANT_DOMAINS_ENABLED        BASE_TENANT_SECURITY_ENABLED
BASE_TENANT_PASSWORDLESS_ENABLED
BASE_TENANT_METERING_ENABLED       BASE_TENANT_FILES_ENABLED
BASE_TENANT_TRANSFER_ENABLED       BASE_TENANT_CONNECTIONS_ENABLED
BASE_TENANT_WEBHOOKS_ENABLED       BASE_TENANT_LANGUAGES_ENABLED
BASE_TENANT_SOCIAL_ENABLED         BASE_TENANT_SEQUENCES_ENABLED
BASE_TENANT_ONBOARDING_ENABLED     BASE_TENANT_SUPPRESSIONS_ENABLED
BASE_TENANT_GDPR_ENABLED           BASE_TENANT_PRESALE
```

All default to on except `BASE_TENANT_PRESALE`, which closes standard
registration and is therefore not something to inherit from an upgrade.
Active sessions belong to the security module and have no switch of their own.

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
vendor/bin/pest
```

The suite runs on Orchestra Testbench against an in-memory SQLite database, and
fails on deprecations, notices and warnings — a green run means a clean one.

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

    public function test_editors_are_scoped_to_their_account(): void
    {
        $this->assertPermissionIsAccountScoped($account, $otherAccount, $editor, 'invoices.edit');
    }
}
```

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
| [`docs/PRODUCT-SPEC.md`](docs/PRODUCT-SPEC.md) | Product specification |

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

## Licence

Proprietary. See [`docs/LICENSE.md`](docs/LICENSE.md).

> Before this repository is published: the licence file and `composer.json`
> still carry placeholder holder and author details, and `homepage` is unset.
