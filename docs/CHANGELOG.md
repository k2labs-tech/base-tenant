# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Outbox with deduplication, retries and a visible dead-letter queue
- API routes with per-account keys, scopes and rate limits
- Trace id propagation and structured JSON logging

## [3.0.0] - 2026-09-19

First release published on Packagist, as `k2labs/base-tenant`. Upgrading from
2.x is covered in [`UPGRADE.md`](UPGRADE.md#from-2x-to-30).

### Added

- **Capability modules, each behind its own switch:** usage metering, files,
  imports and exports, the module generator, connections, signed outbound
  webhooks, runtime languages, social login, sequences, onboarding, email
  suppressions, GDPR and pre-sale. A module that is off registers no routes, no
  menu entries and no scheduled work.
- **Domains:** a subdomain per customer, and custom domains served only once a
  TXT record proves the customer controls them, re-verified daily.
- **Per-tenant security policies:** enforced two-factor with a grace period,
  allowed email domains for invitations, an IP allowlist with a warn-first mode
  and an idle session timeout, with four opt-in middleware.
- **Active sessions** with remote revocation that works on any session driver.
- **Passwordless sign-in:** single-use magic links and WebAuthn passkeys on
  `web-auth/webauthn-lib`.
- **GDPR erasure:** `GdprEraser` implementations, listed in `gdpr.erasers`, run
  whenever a user is force-deleted, whichever path deletes it.
- `TenancyAssertions` is now autoloaded with the package, so host applications
  can use it without touching their `composer.json`.

- **Sistema visual completo en las pantallas de gestión.** Cabecera de página con
  recuento, migas derivadas del propio menú, barra de herramientas con tres zonas
  (buscar, filtrar, ver), celda de identidad compuesta, columnas de estado con
  punto y palabra, conmutador de densidad que sobrevive al refresco, cabecera de
  tabla fija, esqueleto de carga y estados vacíos diseñados. Documentado en
  `docs/superpowers/specs/2026-08-03-acabado-visual-design.md`, con la
  reconciliación de lo que se cumplió y lo que no.
- Las cinco pantallas tabulares y notificaciones comparten ahora la misma
  gramática, con dos excepciones declaradas: features no ofrece tamaño de página
  porque no pagina, y notificaciones no tiene zona de «ver».

### Fixed

- **La columna de roles nunca mostró nada en una instalación real.**
  `UserManager` consultaba la clase `User` del paquete en lugar del modelo
  configurado; los roles se guardan con el `model_type` del modelo de la
  aplicación, así que el morph no casaba. La suite no podía verlo porque en sus
  tests el modelo configurado es el del paquete: ahora hay un fixture que simula
  el de la aplicación anfitriona.
- **El armazón se apilaba en vertical y ningún componente llevaba estilo.** El
  `app.css` de la aplicación no importaba la hoja de Flux, que es de donde sale
  el `grid-template-areas` del shell. El instalador la añade ahora, junto al tema
  del paquete.
- **El conmutador de apariencia solo afectaba a los componentes de Flux.**
  Faltaba declarar `@custom-variant dark`, así que las utilidades `dark:`
  compilaban contra `prefers-color-scheme` y seguían al sistema operativo
  mientras el conmutador ponía la clase `.dark` en el `<html>`.
- **Las etiquetas de los menús salían en crudo tras separar el código.** Viven en
  la base de datos con el prefijo del paquete, y el transformador solo reescribe
  ficheros. El scaffold las reescribe ahora y vacía la caché del árbol.
- **`kit:install --own` fallaba al separar el paquete** justo después de
  copiarlo: el estado de instalación se escribía solo en el fichero de
  configuración, y el comando siguiente, en el mismo proceso, leía el valor de
  arranque.
- **La pantalla de verificación de email daba un 500** en cualquier aplicación
  que usara las rutas del paquete: la notificación de Laravel firma su enlace
  desde `verification.verify` y el paquete registra `base-tenant.verification.verify`.
- **El login con doble factor no tenía salida.** `TwoFactorChallenge` no
  declaraba layout, así que caía al de la aplicación, que el paquete no publica.
- Una tabla desbordada (`?page=99`) anunciaba «aquí no hay nada» sobre datos que
  sí existen.

### Changed

- **The package is now `k2labs/base-tenant`,** installed from Packagist. It was
  `base/tenant`, consumed from a path or VCS repository. The PHP namespace,
  config keys, commands and view names are unchanged; the directory under
  `vendor/` moves to `vendor/k2labs/base-tenant/`, and the installer migrates
  the paths it wrote into `resources/css/app.css`. **Breaking.**
- **Licence:** source-available. Use and modification inside your own
  applications, commercial ones included, is permitted; redistribution is not.
  See `LICENSE.md`, now at the repository root.
- The package depends on `laravel/framework` instead of three loose
  `illuminate/*` components, which the framework replaces.
- Every command lives under `k2labs-base:`; the `base-tenant:*` names remain as
  deprecated aliases until 4.0.
- **Laravel 13 is now required.** The package previously declared `^12.0|^13.0`,
  but that had stopped being true: `pestphp/pest-plugin-laravel ^5.0` requires
  `laravel/framework ^13.23`, so the test suite could not even be installed on
  Laravel 12, and Composer's advisory policy blocks every Laravel 12 release
  reachable from these dependencies. The constraint now says what is actually
  supported. `orchestra/testbench` moves to `^11.0` and Pest to `^5.0`.
  **Breaking for consumers still on Laravel 12.**
- The suite now fails on deprecations, notices and warnings
  (`failOnDeprecation`, `failOnNotice`, `failOnWarning`). It passes clean on
  Laravel 13.23 — previously a green run proved nothing about deprecations,
  because PHPUnit was not configured to escalate them.

## [2.5.0] - 2026-08-01

### Added

- The installer asks for the database connection. It offers sqlite, mysql,
  mariadb and pgsql, asks only what the chosen driver needs, **tests the
  connection before writing anything**, and re-asks on failure showing the
  driver's own error. It runs before every other question, because the
  installer migrates and seeds and discovering the wrong target afterwards is
  expensive to undo. `--database` forces the question when the current
  connection already works
- `DatabaseConfigurator`, which writes `.env` and reconfigures the running
  process, so the migrations in the same command go to the database just chosen
- `EnvironmentManager::setValues()` for arbitrary keys, quoting values that
  would otherwise break the line
- `AdminUserSeeder`, idempotent, reading `base-tenant.admin.*`. The installer
  now delegates to it instead of carrying its own copy
- `base-tenant.admin` configuration for the seeded administrator

### Fixed

- **`migrate:fresh --seed` left the application unusable.** It ran the
  migrations and stopped: no permissions, no roles, no menus and no way to sign
  in. The kit's `DatabaseSeeder` now calls `InitialLoadSeeder` and
  `AdminUserSeeder`, so rebuilding from scratch produces a working application
- **Seeding crashed under `WithoutModelEvents`** — the trait Laravel's own
  `DatabaseSeeder` ships with. `Role` derived its NOT NULL `key` column in a
  `saving` listener, which that trait silences, so seeding died on an integrity
  constraint. The derivation moved to an attribute mutator, which no trait can
  mute
- **Migrations failed on MySQL.** `2026_05_15_000002_update_user_invites_table`
  added `accepted_at` with `->after('expires_at')`, but `expires_at` is created
  by a later migration. MySQL rejects an AFTER clause naming a column that does
  not exist; SQLite ignores AFTER entirely, which is why every previous run
  passed. The whole schema has now been migrated and seeded against MySQL 8.4
  as well as SQLite

## [2.4.0] - 2026-08-01

Closes the two management screens the K2 functional spec asks for by name and
the package did not have: KB-05 wanted feature flags "with an admin UI", KB-06
wanted settings "with UI scaffolding".

### Added

- `FeatureManager` — what an account is entitled to, and where each answer comes
  from. Lists the plan baseline, marks which values are overridden, toggles
  booleans in one click, edits numeric allowances with an optional expiry, and
  resets a feature back to the plan. Staff may switch which account they are
  looking at
- `AccountSettings` — a form built from the registered `SettingsSchema` classes.
  A declared property type picks the control, so adding a setting means adding a
  property and nothing else
- `Settings::register()` and `base-tenant.settings.schemas` for declaring schemas,
  plus `SettingsSchema::fields()` and `::label()` for rendering them
- Routes `/features` and `/settings`, both entries in the settings menu, gated on
  `features.view` and `settings.view`
- 17 tests covering both screens, including that a tenant administrator can see
  their entitlements but cannot grant themselves more

### Fixed

- `Flux::toast()` was called with only `variant` and `heading` in `RoleManager`
  and `NavigationManager`. Flux takes the message as its first argument, so those
  calls threw `ArgumentCountError` after the write had already happened: the data
  was saved but the component came back with no state. Every toast now passes its
  text
- `ConflictDetector::isAlreadyInstalled()` read only configuration signals — the
  published config, the environment variables and a `User` model extending the
  package. A starter kit ships all three on purpose, so a project created from
  one declared itself already installed and the installer refused to run. It now
  also checks whether the schema exists

## [2.3.0] - 2026-07-31

### Added

- **Laravel 13 support.** The package now declares `^12.0|^13.0` and the full
  suite passes on both, verified against each: Laravel 12.64 with testbench
  10.11 and Pest 4, and Laravel 13.23 with testbench 11.1 and Pest 5. No
  application code changed — nothing the package does was touched by the
  framework's breaking changes

### Changed

- `spatie/laravel-permission` raised from `^6.0` to `^8.0`. Two major versions,
  but the API this package builds on is unchanged: the `PermissionsTeamResolver`
  contract, `Guard::getNames`, the teams configuration keys and the table schema
  are all the same, and version 8 supports Laravel 12 as well as 13. The whole
  suite passed on the bump without a single code change
- Dependencies widened to admit their Laravel 13 releases: `laravel/tinker`
  `^2.9|^3.0`, `pragmarx/google2fa-laravel` `^2.3|^3.0`, `spatie/laravel-flare`
  `^2.2|^3.0`. Development: `orchestra/testbench` `^10.0|^11.0`, `pestphp/pest`
  `^3.0|^4.0|^5.0`

## [2.2.0] - 2026-07-31

First release verified end to end against a real Laravel application, which is
how the fixes below were found.

### Changed

- **`livewire/flux-pro` is no longer required.** The package uses only free Flux
  components — `toast`, `modal`, `button`, `heading`, `input` and `icon` all ship
  with `livewire/flux` — so Pro moved to `suggest`. The installer asks whether to
  install it and adds it to the application's `composer.json` when you say yes.
  This also removes the licence barrier to distributing anything built on the
  package
- `base-tenant:eject` takes `--force`, so a scripted install can run it without
  a prompt

### Fixed

- Role assignment failed with *"The given role or permission should use guard
  `` instead of `web`"* in any application whose `config/auth.php` points at its
  own `User` subclass: spatie infers the guard by matching the class against the
  auth providers, and the package's model matched none of them. It now falls
  back to the configured guard
- The installer's rewrite of `models.user` in the published config never fired.
  It matched a literal `\Base\Tenant\Models\User::class`, but the published file
  refers to the model through a `use` statement. Matched by pattern now
- `Account::factory()` and `User::factory()` were unusable from a host
  application: Laravel resolves factories from the application namespace and
  never finds a package model. Both models now name their factory

## [2.1.0] - 2026-07-31

### Added

- `base-tenant:scaffold` — copies the package code into the application and
  rewrites it to belong there
  - `ScaffoldPlan` works from a deny-list, so a directory added to `src/` is
    scaffolded by default instead of having to be declared
  - `CodeTransformer` rewrites namespaces, Blade component tags, and view and
    translation namespaces, telling the two `base-tenant::` meanings apart
  - `ServiceProviderGenerator` renders `app/Providers/TenancyServiceProvider.php`
    from the package's own registration maps, so it cannot drift
  - `ScaffoldConflictDetector` only reports destinations that exist *and* differ
  - `--dry-run`, `--overwrite`, `--skip-existing` and `--force`
- `base-tenant:eject` — transfers dependencies and removes the package
  - requirements and repositories are read from the package manifest, so a new
    dependency is transferred without updating the command
  - `app/helpers.php` is added to the application's autoloaded files
  - verifies the scaffolded files are present before touching anything
  - backs up `composer.json` and restores it if the transfer fails
  - `--dry-run` and `--keep-package`
- `installation_state` in configuration: `fresh`, `installed`, `scaffolded`, `ejected`
- `stubs/TenancyServiceProvider.php.stub`
- `docs/SCAFFOLD-EJECT.md`
- Registration maps exposed as static methods on `BaseTenantServiceProvider`
  (`policies()`, `middlewareAliases()`, `singletons()`, `livewireComponents()`,
  `bladeComponents()`) so the generated provider reads them rather than copying
- 14 tests covering the plan's coverage of `src/`, PHP validity of every
  scaffolded file, the absence of package references in the output, and the
  dependency transfer

### Changed

- `BaseTenantServiceProvider` stands down once `installation_state` is
  `scaffolded` or `ejected`, registering only the publish tags and the scaffold
  commands. Without this the application would have two definitions of every
  route
- Migrations are copied verbatim under their original filenames rather than
  merged into one file. The previous consolidator parsed `Schema::create` blocks
  with a regular expression that did not match closures declaring a `void` return
  type, and would have silently dropped the permission, menu and feature tables
- Documentation no longer advertises `vendor:publish --tag=base-tenant-views`,
  a tag that has never been registered; customising the views is what
  `base-tenant:scaffold` is for

## [2.0.0] - 2026-07-30

Reworks the two foundations the package was missing: a real tenancy layer and
granular permissions. **Read `docs/UPGRADE.md` before upgrading** — role storage
and several APIs changed.

### Added

- Tenancy core
  - `TenantManager` and `Tenant` facade: `current()`, `set()`, `runFor()`,
    `runWithout()`, `eachAccount()`, `cacheKey()`, `channel()`
  - Resolver chain: domain/subdomain, Sanctum token, session, authenticated user
  - `BelongsToAccount` trait with `AccountScope`, automatic `account_id` stamping
    and the `acrossAccounts()` / `forAccount()` scopes
  - `QueueTenancy`: the account is stamped into every job payload and restored by
    the worker
  - `TenantCache` for account-namespaced cache keys
  - `TenantChanged` event
  - `accounts.domain`, `accounts.subdomain` and `accounts.status` columns
  - `personal_access_tokens.account_id` so API calls resolve a tenant without a session
- Granular permissions on `spatie/laravel-permission` with `account_id` as the team key
  - `Permission` model and a configurable permission catalogue with groups
  - `Role` extends the spatie model; `name` is the identifier, `display_name` the
    label, `key` kept as a synced alias
  - Roles are global (`account_id` null) or owned by one account
  - `HasRolesAndPermissions` trait: `hasPermission()`, `hasPermissionInAccount()`,
    `rolesForAccount()`, `belongsToAccount()`, `isSuperAdmin()`
  - `TenantTeamResolver` binds spatie's team id to the tenant context
  - `PermissionRegistry` syncs the catalogue and global roles, expanding `*` and
    `users.*` style wildcards
  - `RoleManager` Livewire component: role × permission matrix, per-account role creation
  - Policies for `User`, `Account`, `Role` and `UserInvite`
  - `Gate::before` bypass for `is_admin` users
- Database-driven navigation
  - `menus` and `menu_items` tables; `Menu` and `MenuItem` models
  - `MenuManager` with programmatic registration, per-account overrides,
    permission and feature filtering, badge callbacks and versioned caching
  - `NavigationManager` Livewire component for reordering, renaming and hiding
  - `base-tenant:sync-menus` command
- Feature flags per account
  - `features` table with typed values and expiry
  - `Feature` facade layering account overrides over the subscription plan
- Typed settings: `SettingsSchema`, `SettingsBag`, `Settings` facade
- `AccountDeletionService`: driver-agnostic scan for tables still referencing an account
- Multi-tenant test kit: `assertTenantIsolated`, `assertJobCarriesTenant`,
  `assertPermissionIsAccountScoped`, plus `createAccount()`, `createUser()`,
  `createRole()` and `actingAsTenant()` helpers

### Fixed

- `AccountManager` queried the host application's `projects` and `translations`
  tables, throwing on any project without them
- `AccountManager` detected related records through `sqlite_master` and
  `PRAGMA table_info`, so the integrity guard did nothing on MySQL or PostgreSQL
- Components checked for `project-admin` and `project-collaborator`, roles absent
  from the configuration, which returned 403 to every non-admin on a clean install
- `hasRole()` read from the session: it returned every role across every account
  outside a request, and returned the authenticated user's roles when asked about
  a different user
- Editing a user detached their roles in every account, not just the one being edited
- `User::addRole()` and the `sync()` branches dropped the account from the pivot
- `User::isAdmin()` read a non-existent `admin` column
- `applyCurrencyFormat()` read a non-existent `decimals_pointer` column and swapped
  the decimal and thousands separators
- `InvitationManager` resolved invitations by id with no account check
- Removed `User::hasAlerts()`, which returned a random number
- Livewire components rendered into the host application's `layouts.app` instead of
  a configurable layout

### Changed

- Navigation is rendered from the database; the hardcoded sidebar with placeholder
  metrics is gone
- `FeatureService` keeps its API but now resolves through the three-layer
  `FeatureSet`
- `SetAccountContext` puts the resolved account in context instead of writing a
  session key directly
- `ActivityLog` and `UserInvite` are scoped by `BelongsToAccount`; their bespoke
  `forAccount()` scopes were removed in favour of the trait's
- `base-tenant:sync-roles` now syncs permissions too, and takes `--show`
- Seeders and the installer also sync the permission catalogue and the menus

### Deprecated

- `User::storeRolesSession()` is a no-op kept for compatibility; roles no longer
  live in the session
- `roles.key` is a read-only alias of `roles.name`
- The `role_user` table is no longer written to; its contents are migrated to
  `model_has_roles` and the table is left in place

## [1.2.0] - 2026-05-15

### Added
- Activity logging / audit trail system
  - `ActivityLog` model with polymorphic causer/subject
  - `ActivityLogService` for programmatic logging
  - `LogsActivity` trait for automatic model change tracking
  - `base-tenant:prune-activity-log` Artisan command
  - `ActivityLog` Livewire component for viewing audit trail
- Generic key-value settings system
  - `Setting` model with polymorphic ownership
  - `HasSettings` trait for any model
  - `SettingService` with cascade resolution (user > account > default)
  - `tenant_setting()` global helper function
- User invitation system enhancements
  - `InvitationService` with send/resend/accept/revoke
  - `InvitationManager` Livewire component
  - Token-based acceptance (works before/after signup)
  - Auto-deletion of previous pending invites on resend
  - `InvitationAcceptController` for public acceptance
- `HasFeature` middleware for plan-based route protection
  - Parametric usage: `base-tenant.feature:feature_name`
  - Redirect to upgrade page or 403 for JSON

## [1.1.0] - 2025-12-02

### Added
- Database notification system
  - `BaseTenantNotification` abstract class with standardized data structure
  - `NotificationBell` Livewire component with dropdown and polling
  - `Notifications\Index` full-page notifications component
  - `NotificationController` for mark-as-read actions
  - `NotificationService` for dispatching and managing notifications
  - Configurable polling interval, dropdown limit, per-page
- Daily notification summary infrastructure
  - `daily_notification_summary` column on accounts (default true)
  - `daily_notification_summary` column on users (nullable, inherits from account)
  - `shouldReceiveDailySummary()` cascading preference method
- Force password change feature
  - `EnsurePasswordChanged` middleware (conditionally auto-added to web group)
  - `ForcePasswordChange` Livewire component
  - `WelcomeUserNotification` with temporary credentials
  - `must_change_password` flag on users table
- Account switcher for multi-team mode
  - `AccountSwitcher` Livewire component
  - Handles both single-team and multi-team modes
  - Refreshes session roles on account switch
- Profile management components
  - `UpdateProfileInformationForm` Livewire component
  - `UpdatePasswordForm` Livewire component
  - `DeleteUserForm` Livewire component
- User preferences component
  - `Preferences` Livewire component
  - Locale, timezone, date/time format, currency, separators
  - Daily notification summary preference
- `SetAccountContext` middleware (auto-added to web group)
  - Determines default account on login
  - Priority: last_account_id > account_id > first account
  - Skips system admins

## [1.0.0] - 2025-01-30

### Added
- Initial release of Base Tenant package
- Multi-tenant architecture with Accounts and Users
- Role-based access control (RBAC) system
- Extensible roles configuration (system, customer, custom)
- Stripe subscription management via Laravel Cashier 16
- Two-factor authentication (2FA) support
  - Google Authenticator TOTP
  - QR code generation (SVG)
  - 8 recovery codes with invalidation
  - Encrypted 2FA secrets
- User preferences and localization
  - Timezone support
  - Currency formatting
  - Number formatting
  - Date/time formatting
  - Multi-language support (English, Spanish)
- Livewire 3 components
  - User management (CRUD, impersonation)
  - Account management (CRUD)
  - Two-factor authentication setup
  - Login/Registration/Password reset forms
  - Alerts table
- Laravel 12 compatibility
- Tailwind CSS 3 integration
- Livewire Flux Pro UI components
- Comprehensive testing suite with Pest 3
- Database migrations for all package tables (UUID primary keys)
- Seeders with test users and roles
- Middleware
  - `HasSubscription` - subscription gate with non-production bypass
  - `DoesNotHaveSubscription` - checkout gate
  - `SetLocale` - user locale preference
- Artisan commands
  - `base-tenant:install` - interactive package installer
  - `base-tenant:sync-roles` - sync roles from configuration
- Publishable assets (CSS, JS)
- Publishable configuration file
- Publishable language files
- Factory classes for testing (User, Account)
- API authentication via Laravel Sanctum
- UUID primary keys for all models
- Session-based role caching for performance
- User impersonation (system admins only)
- `@feature()` Blade directive for plan feature gates
- Password reset URL configured to use package routes

### Configuration
- Multi-team toggle
- Subscription settings (product, price, trial, URLs)
- Plan feature gates (boolean + numeric limits)
- Extensible roles system
- Model overrides via config
- Route customization (prefix, middleware, enable/disable)
- UI branding options (name, logo)

### Documentation
- Comprehensive README
- Detailed installation guide
- Frontend customization guide
- Notification system documentation
- Upgrade guide from Laravel 11
- AI context reference for LLM agents
