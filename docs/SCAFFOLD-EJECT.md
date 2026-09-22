# Scaffold and Eject

The package is meant to be used two ways, and to let you change your mind.

**As a dependency.** You `composer update` and get fixes and new features. This is
the default, and where most projects should stay.

**As a starting point.** You copy the code into your application, own it outright,
and remove the package. Updates stop arriving, but nothing about your project
depends on us any more.

Moving between the two is a command, not a rewrite.

## The three states

```
   fresh              installed             scaffolded             ejected
     │                    │                     │                     │
     │  base-tenant:      │  base-tenant:       │  base-tenant:       │
     └──── install ──────▶└──── scaffold ──────▶└──── eject ─────────▶│
                          │                     │
                    package owns          your app owns
                    routes and views      routes and views,
                                          package still installed
```

The current state lives in `config/base-tenant.php`:

```php
'installation_state' => 'installed',
```

Each command refuses to run out of order, so you cannot eject onto code that was
never copied.

## Scaffold

```bash
php artisan base-tenant:scaffold --dry-run   # see the plan first
php artisan base-tenant:scaffold
composer dump-autoload
```

### What moves where

| From the package | Into your application |
|---|---|
| `src/**` | `app/**` |
| `database/migrations` | `database/migrations` |
| `database/factories`, `database/seeders` | same paths |
| `routes/*.php` | `routes/tenant/` |
| `resources/views/components` | `resources/views/components/tenant` |
| `resources/views/**` | `resources/views/tenant/` |
| `resources/lang/{locale}/*.php` | `lang/vendor/tenant/` |
| `resources/lang/*.json` | `lang/` (merged, your strings win) |
| `stubs/module/*.stub` | `stubs/base-tenant/module/` |
| `tests/TenancyAssertions.php` | `tests/TenancyAssertions.php` |

Roughly 200 files. What does **not** move: the package's own service provider,
which is replaced by a generated one, and the installer and scaffold machinery,
which stop being relevant once you own the code.

### What gets rewritten

| Reference | Becomes |
|---|---|
| `namespace Base\Tenant\Menu;` | `namespace App\Menu;` |
| `use Base\Tenant\Models\User;` | `use App\Models\User;` |
| `'Base\\Tenant\\Models\\User'` in config | `'App\\Models\\User'` |
| `<x-base-tenant::application-logo>` | `<x-tenant.application-logo>` |
| `view('base-tenant::livewire.login')` | `view('tenant::livewire.login')` |
| `__('base-tenant::users.title')` | `__('tenant::users.title')` |

Views and translations keep a namespace (`tenant::`) rather than being poured
into `resources/views` and `lang` directly. The files are yours either way, and a
namespace means nothing you already had is silently shadowed by a 60-file drop.

Identifiers are **not** renamed. Route names stay `base-tenant.dashboard`,
Livewire components stay `base-tenant.user-manager`, and the config file stays
`config/base-tenant.php`. Renaming them would mean rewriting every `route()` call
and every `<livewire:>` tag in your own code too — a separate decision, not
something a copy command should make for you.

JSON translation files go to the `lang/` root rather than into the namespace:
Laravel has no namespaced JSON translations, so a file anywhere else is simply
never loaded. If you already have a `lang/es.json`, the keys are merged and
yours win.

The PHP ones go to `lang/vendor/tenant/`, which is where Laravel keeps
namespaced translations. Under `lang/tenant/` they load just the same, but every
tool that lists the locales by reading `lang/` — including `k2labs-base:lang:status`
— counts `tenant` as a language, forever at 0% coverage.

The module generator's templates travel too. Once the package is gone there is
no vendor directory to read them from, and they are rewritten on the way out, so
the modules you generate afterwards import your namespaces rather than ones that
no longer exist.

### The generated service provider

`app/Providers/TenancyServiceProvider.php` reproduces everything the package's
provider did, in your namespaces:

- spatie/laravel-permission configured with `account_id` as the team key and
  `TenantTeamResolver` reading the tenant context
- the container singletons, the entries that cannot be autowired (`DnsLookup`,
  `LangFileWriter`) and the ones built by a named constructor
- the four policies and the `Gate::before` super-admin bypass
- `QueueTenancy`, so jobs keep carrying the tenant
- menu registration and badge resolvers
- the six middleware aliases, plus `SetAccountContext` pushed onto the `web` group
- every Livewire component and the two Blade layout components
- the surviving Artisan commands
- the scheduled maintenance work, through `App\Support\ScheduledTasks`
- the password reset and email verification URLs
- the social login providers, the suppression guard, the invitation listener and
  the security policy lifecycle

It is generated by reading the package's own registration maps, not from a copy
kept in a stub, so a component added to the package appears here without anyone
remembering this file exists. A test compares the two providers call by call, so
a behaviour added to the package's `boot()` and not to the generated one fails
the package's own suite rather than a customer's application. It is registered in
`bootstrap/providers.php` automatically.

Once it exists, it is yours. Edit it.

### After scaffolding, before ejecting

The package stands down: seeing `installation_state = scaffolded`, its provider
registers only the publish tags and the scaffold commands, and leaves routes,
views, components and policies to yours. Without that, every route would have two
definitions.

This is the state to sit in for a while. Run your suite. Read the diff. The
package is still there if you want to go back — set `installation_state` to
`installed` and it takes over again.

### Your own references to the package

Eject rewrites what it copies. What it cannot copy is the code you wrote: a
seeder that imported `Base\Tenant\Database\Seeders\InitialLoadSeeder`, a test
that used the package's models, a job that called one of its services. Once the
package is gone, those imports point at nothing.

So before removing it, eject scans `app/`, `database/`, `routes/` and `tests/`
for references to the package, applies the same rewrite the copied code got, and
lists every file it touched. Read that diff before committing. A file naming the
package's own service provider is left alone and reported separately: there is no
copy of that class, `App\Providers\TenancyServiceProvider` replaces it.

### Conflicts

Destination files that already exist and differ are listed before anything is
written, with the choice to overwrite, keep them, or cancel. Files that are
already identical are not counted as conflicts, so re-running scaffold after a
package update is quiet.

```bash
php artisan base-tenant:scaffold --overwrite       # take the package version
php artisan base-tenant:scaffold --skip-existing   # keep yours
php artisan base-tenant:scaffold --force           # refresh an already-scaffolded project
```

### Migrations are copied, not consolidated

Each migration keeps its original filename, which is the name already recorded in
your `migrations` table — so nothing re-runs. An earlier design merged all of them
into one file by parsing `Schema::create` blocks with a regular expression; that
parser silently dropped any migration whose closure signature it did not
recognise. Copying is duller and does not have failure modes.

## Eject

```bash
php artisan base-tenant:eject --dry-run
php artisan base-tenant:eject
composer update && composer dump-autoload && php artisan optimize:clear
```

It checks that every file the scaffold should have written is actually there
before doing anything, and refuses — or asks — if any are missing.

Then it:

1. copies the package's requirements into your `composer.json`, skipping what you
   already have, and adds the Flux Pro repository if you need it
2. adds `app/helpers.php` to your autoloaded files
3. sets `installation_state` to `ejected`
4. runs `composer remove k2labs/base-tenant`

`composer.json` is backed up first and restored if anything throws.

```bash
php artisan base-tenant:eject --keep-package   # transfer dependencies, remove by hand
```

### The stylesheet comes with you

The scaffolded views use the package's state colour scales — `bg-danger-500`,
`text-warning-600`, `bg-info-500` — which are defined in the package's theme
file, not in Tailwind. So eject takes the theme with it rather than leaving the
views pointing at a scale that no longer exists:

1. `resources/css/base-tenant.css` is copied out of the package and into your
   `resources/css/base-tenant.css`
2. the import in `resources/css/app.css` is repointed from the `vendor/` path to
   `@import './base-tenant.css';`
3. the `@source` lines that pointed at the package's views are removed — the
   scaffolded views under `resources/views/` are already covered by the
   application's own `@source`

The copied theme is yours from that point on: edit it, rename the scales, or
fold its `@theme` block into your own. Nothing else reads it. If
`resources/css/base-tenant.css` already exists and differs from the package's,
eject keeps yours and says so; pass `--force` to overwrite it.

An `app.css` the installer never touched is left exactly as it is.

`config/base-tenant.php` stays: the copied code reads it. Rename it if you like,
updating the `config()` calls in `app/` to match.

## Proven end to end

Both commands have been run against a real application built from
`k2labs/starter-kit`:

- `scaffold` wrote all 255 files, generated `app/Providers/TenancyServiceProvider.php`
  and registered it in `bootstrap/providers.php`
- the scaffolded application booted with 48 routes and no duplicates, and its
  test suite passed against the copied `App\` classes
- `eject` transferred 9 dependencies, removed the package, and the application
  still booted and passed its suite with `vendor/k2labs/base-tenant` gone

Three bugs surfaced in that run and are fixed: guard inference for the permission
layer, the installer's rewrite of `models.user`, and factory resolution from a
host application.

Still do the first run on your own project with `--dry-run`, on a branch. Your
application has code ours does not.

## What is guaranteed by tests

`tests/Feature/ScaffoldTest.php` asserts, on every run:

- every directory under `src/` is in the plan unless explicitly excluded — the
  list is a deny-list, so a new subsystem is covered by default
- every migration on disk is in the plan
- every scaffolded PHP file passes `php -l` and contains no `Base\Tenant`
- every Blade view loses `x-base-tenant::` and `'base-tenant::'`
- the generated provider is valid PHP, registers every Livewire component the
  package registers, and contains no unreplaced placeholders
- dependency transfer skips what the application already has and can roll back

The first two are the ones that matter. An earlier version of this command
enumerated the directories to copy, and quietly missed six subsystems when they
were added.
