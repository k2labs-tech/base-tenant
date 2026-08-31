# base/tenant — Agent index

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-12

You are working in a Laravel application built on the `base/tenant` package.
This directory is the map. Read this file before writing code.

---

## The golden rule

**Check the capability map below before implementing anything.**

Most of what a multi-tenant SaaS needs is already here: tenancy, permissions,
menus, settings, activity, plan features, usage metering, file storage,
imports and exports, correlative numbering, languages, social login,
third-party credentials, outbound webhooks, onboarding, email suppression and
GDPR. Reimplementing one of them produces code that looks right, passes its own
tests, and quietly breaks tenant isolation, plan limits or the audit trail —
because those live in the layer you went around.

If the map has no row for what you need, build it. But look first.

---

## Capability map

| You need… | Use this | Doc | Do not |
|---|---|---|---|
| Scope a model to the account | `BelongsToAccount` trait | [01](01-architecture.md) | `where('account_id', session(...))` |
| Run code as another tenant | `Tenant::runFor($account, fn () => ...)` | [01](01-architecture.md) | Set a session variable and hope |
| Keep tenancy inside a queued job | Nothing — it is automatic | [01](01-architecture.md) | Pass `account_id` into the job payload |
| Gate a route or screen by role | `hasPermission()`, `can`, policies | [02](02-conventions.md) | Compare role names in a view |
| Gate a feature by plan | `Feature::active()`, `@feature` | [02](02-conventions.md) | Read `config('base-tenant.plans')` directly |
| Count consumption, enforce a limit | `Meter` facade, metered features | [10](10-metering.md) | Ad-hoc counters, `count()` against tables |
| Refuse work when the plan is full | `Meter::incrementOrFail()`, `base-tenant.metered` middleware | [10](10-metering.md) | Check, then increment |
| Upload files (multi, S3/Vapor) | `HasFiles` + `base-tenant.files.uploader` | [11](11-files.md) | `WithFileUploads`, or your own upload route |
| Show storage used | `base-tenant.files.usage-badge` | [11](11-files.md) | `sum('size')` on every page load |
| Add a navigation entry | `Menu::register()` | [02](02-conventions.md) | Edit a Blade partial |
| Store per-account configuration | Typed settings (`Settings::register()`) | [02](02-conventions.md) | Add columns to `accounts` |
| Record who did what | `LogsActivity` trait | [02](02-conventions.md) | Write to a log file |
| Offer a new language | `Language::enable('fr')` | [20](20-languages.md) | Add a locale to a config array |
| Let people sign in with Google | Social login, already built | [21](21-social-login.md) | Wire Socialite yourself |
| Number something consecutively | `Sequence::next()` | [22](22-sequences.md) | `max(id) + 1`, or an exposed autoincrement |
| A new CRUD area of the app | `k2labs-base:make-module` | [13](13-generator.md) | Write the model, screens and policy by hand |
| A customer's credentials for a third party | `Connection::for($account)->client()` | [14](14-connections.md) | API keys in settings or in their own columns |
| Build a link to a customer's space | `Domain::urlFor($account)` | [15](15-domains.md) | `config('app.url')`, which does not know the customer |
| Let a customer use a domain of their own | `Domain::addDomain()` then `Domain::verify()` | [15](15-domains.md) | Serve the hostname before it is verified |
| Respect the customer's own security rules | `Security::` and the three middleware | [16](16-security.md) | Read the `security` settings group yourself |
| Restrict who can be invited into an account | `Security::allowsEmail()` | [16](16-security.md) | A check in one screen only |
| List or end somebody's open sessions | `Sessions::` | [16](16-security.md) | Delete rows from Laravel's `sessions` |
| Sign in without a password | `MagicLink::` | [17](17-passwordless.md) | Log the user in without checking 2FA |
| Tell an external system something happened | `Webhook::dispatch()` | [14](14-connections.md) | A loose HTTP call, unsigned and without retries |
| Guide a new account through setup | Onboarding steps in config | [23](23-onboarding.md) | A boolean column per step |
| Stop mailing an address that bounced | Nothing — the guard is global | [24](24-suppressions.md) | A check at one call site |
| Export or erase somebody's personal data | A `GdprExporter` + the purge command | [25](25-gdpr.md) | `$user->toArray()`, hash included |
| Launch before the product exists | Pre-sale mode | [26](26-presale.md) | A separate marketing site with its own list |
| Import a CSV the user uploaded | An `Import` handler + `Transfer::import()` | [12](12-transfer.md) | Parse it in the request |
| Export data to a file | An `Export` handler + `Transfer::export()` | [12](12-transfer.md) | `get()` the whole table |
| Build a management screen with a table | `InteractsWithTable` + the table pattern | [02](02-conventions.md) | A bare `<table>` and a `foreach` |
| Add a package command | `k2labs-base:` namespace | [02](02-conventions.md) | Any other prefix |
| Put a screen inside the app chrome | `#[Layout('base-tenant::layouts.app')]` | [06](06-ui.md) | Render a bare component with no layout |
| Let a user change account | `base-tenant.account-switcher` | [06](06-ui.md) | Write to `current_account_id` yourself |

---

## Files

| File | What is in it |
|---|---|
| [01-architecture.md](01-architecture.md) | Tenancy, the resolver chain, `BelongsToAccount`, context in jobs and cache |
| [02-conventions.md](02-conventions.md) | Permissions, menus, settings, activity, translations, commands, testing, the table pattern |
| [03-models.md](03-models.md) | Every model and the tables behind them |
| [04-services.md](04-services.md) | The service classes and the eight middleware aliases |
| [03-models.md](03-models.md) | Every model the package ships, its table, casts, relations and scopes |
| [05-configuration.md](05-configuration.md) | `config/base-tenant.php` key by key, environment variables, default roles |
| [06-ui.md](06-ui.md) | Layouts, embeddable components, Blade components and directives |
| [07-screens.md](07-screens.md) | Which screen sits at which route, and the full component registry |
| [10-metering.md](10-metering.md) | Usage metering and plan limits (M1) |
| [11-files.md](11-files.md) | File storage and the upload flow (M2) |
| [12-transfer.md](12-transfer.md) | Imports and exports (M3) |
| [13-generator.md](13-generator.md) | Generating new vertical modules (M4) |
| [14-connections.md](14-connections.md) | Per-account credentials and outbound webhooks (M5) |
| [15-domains.md](15-domains.md) | Subdomains and verified custom domains (M6) |
| [16-security.md](16-security.md) | Per-tenant security policies and active sessions (M7) |
| [17-passwordless.md](17-passwordless.md) | Magic links; passkeys not built (M8) |
| [20-languages.md](20-languages.md) | Languages enabled at runtime (Q1) |
| [21-social-login.md](21-social-login.md) | Google, LinkedIn and Microsoft sign-in (Q2) |
| [22-sequences.md](22-sequences.md) | Correlative numbering per account (Q3) |
| [23-onboarding.md](23-onboarding.md) | The setup checklist (Q4) |
| [24-suppressions.md](24-suppressions.md) | Addresses that must not be mailed (Q5) |
| [25-gdpr.md](25-gdpr.md) | Personal data export, purge and terms (Q6) |
| [26-presale.md](26-presale.md) | Pre-sale, waiting list and founding places (Q7) |

Every module in the v2 specification is built and documented. A capability
with no file here does not exist — do not write code against it.

---

## Non-negotiables

These are not style preferences. Each one is a defect that has actually
shipped in this codebase.

1. **Every user-facing string goes through `__()`.** No exceptions, including
   validation messages, mail bodies and toasts. Add the key to *both*
   `resources/lang/en` and `resources/lang/es`.
2. **A tenant-owned model uses `BelongsToAccount`.** Not a manual `where`.
3. **Never trust the client about size, type or ownership.** Re-read it
   server-side from the record that exists.
4. **Destructive confirmations use `flux:modal` and name the object.** Never
   `wire:confirm` — the native dialog is unstyled, untranslatable, and some
   browsers let a user silence it for the session.
5. **A guard needs a test that fails when the guard is removed.** A test that
   passes either way is worse than no test: it reads as coverage.
