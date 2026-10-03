# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Outbox with deduplication, retries and a visible dead-letter queue
- API routes with per-account keys, scopes and rate limits
- Trace id propagation and structured JSON logging

## [3.2.0] - 2026-10-03

### Security

- **Uploaded SVGs could run script in the application's origin.**
  `FileCollection::images()` accepted `image/*`, which includes
  `image/svg+xml`, and `files.public` served the file inline with no CSP: an
  SVG carrying `<script>` in a public collection was a stored XSS behind a
  link that needs no session. Two layers now:
  - a MIME wildcard (`image/*`, `text/*`, `application/*`) never matches active
    content -- SVG, HTML, XHTML, XML (`*+xml` included) and JavaScript. A
    collection that wants one names it, or uses `images(..., svg: true)`.
  - `files.public` and `files.show` serve active content with
    `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`
    and `X-Content-Type-Options: nosniff` (now on every file);
    `files.public` sends it as `attachment`. Scripts are served as
    `text/plain`. Signed URLs for active content request
    `ResponseContentDisposition: attachment`.
- **Image renditions are protected against decompression bombs.** A small PNG
  declaring a huge canvas made GD allocate gigabytes and killed the worker.
  `ImageVariants` now reads the dimensions from the header first and skips the
  renditions above `files.max_image_pixels`, keeping the original and logging
  a warning.

### Added

- `files.max_image_pixels` (`BASE_TENANT_FILES_MAX_IMAGE_PIXELS`), default
  40,000,000; `0` or `null` removes the limit.
- `FileCollection::images(..., svg: true)`.
- `Base\Tenant\Files\ActiveContent` -- the list of types served sandboxed,
  and the headers they get.
- `File::variantsSize()` -- bytes the renditions occupy on the disk.
- **Outbound webhook attempt history.** New table `outbound_webhook_attempts`
  and model `OutboundWebhookAttempt` (`webhooks.models.attempt`): one row per
  try with `attempt`, `outcome` (`delivered`, `failed`, `postponed`),
  `status_code`, `error`, `duration_ms` and `attempted_at`.
  `OutboundWebhookDelivery::attempts()`. On by default (`webhooks.log_attempts`);
  the delivery row is unchanged and still keeps the last response. Not pruned
  by the package.
- `webhooks.redact_errors` (default `false`): stored errors, on the attempt and
  the delivery, have every URL cut down to `scheme://host[:port]`. Errors on
  attempts are capped at 2000 characters. `Webhook::redactError()`.
- **Unsigned endpoints.** `webhooks.allow_unsigned` (default `false`) and
  `Webhook::register(..., signed: false)`: an endpoint without a secret gets
  its deliveries with no signature header. `outbound_webhooks.secret` is
  nullable; `OutboundWebhook::isSigned()`. Off, an endpoint with no secret is
  refused as before.
- **Pause for degraded endpoints.** `webhooks.degraded_cooldown` (seconds,
  default `null`): a degraded endpoint receives nothing for that long after
  its last failed attempt. Deliveries due in the pause are postponed -- left
  pending until it ends, with no attempt spent and a `postponed` attempt row --
  never dropped. New column `outbound_webhooks.last_failed_at`;
  `OutboundWebhook::pausedUntil()`, `Webhook::recordFailedAttempt()`.
- `webhooks.json_flags` (default `0`): flags for the `json_encode()` that
  builds a body, e.g. `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`. The
  signature is over the bytes sent, and retries and redeliveries send the
  stored bytes.

### Fixed

- **The `storage.bytes` gauge drifted upwards with every deleted image.**
  Renditions were added to it when generated, but `FileStore::delete()` only
  took off the original. It now takes off the renditions too, measured on the
  disk before they are removed. Regenerating renditions meters only the
  difference and removes the ones no longer declared, so a retry no longer
  counts them twice.
- `k2labs-base:reconcile-storage` counted only originals, so it took the
  renditions off the gauge and every later delete pushed it below the real
  figure. It now adds the renditions of each file, read from the disk.
- **Outbound webhooks failed under strict tenancy outside `Tenant::runFor()`.**
  With `tenancy.strict` and `on_missing_tenant = throw`, `DeliverWebhook`
  wrote to its delivery with whatever account the worker had -- none, or the
  caller's -- and threw `MissingTenantException` or
  `CrossAccountWriteException`. The job now runs each attempt under the
  delivery's account, and `Webhook::dispatch(..., $account)` and
  `Webhook::redeliver()` queue it under that account, so both work from a
  console command, a scheduled task or another account's context.

## [3.1.0] - 2026-10-01

The package's sign-in can now be used on its own: a host application can keep
its own dashboard and take only the login, registration, second factor and
passkeys. Outbound webhooks can carry a product's own published contract,
tenancy can fail closed, and several 3.0 settings that were declared but never
read now take effect. With the default configuration nothing changes, except
where noted under Security and Changed.

### Security

- **The second-factor challenge trusted the browser with the user id.**
  `TwoFactorChallenge::$userId` was a public Livewire property: changing it let
  someone complete the second factor as another user with one of that user's
  recovery codes, without their password. It is locked now.
- **CSV exports are protected against formula injection.** `Csv::write()`
  prefixes with `'` every cell that starts with `=`, `+`, `-`, `@`, tab or
  carriage return (OWASP), so a value a user typed as `=HYPERLINK(...)` is text
  in whoever opens the export. Plain numbers, signed ones included, are left as
  numbers so sums keep working. On by default; `transfer.csv.escape_formulas`
  turns it off.

### Added

- **Route groups.** `routes.auth.enabled`, `routes.app.enabled`,
  `routes.subscriptions.enabled` and `routes.webhooks.enabled` switch each group
  of routes on its own. Left unset they follow `routes.enabled`, which keeps
  meaning "everything".
- **`routes.auth.laravel_names`.** Registers `login`, `register`, `logout`,
  `password.request`, `password.reset`, `password.confirm`,
  `verification.notice`, `verification.verify` and `two-factor.login` as
  aliases of the package screens, so the `auth`, `verified` and
  `password.confirm` middleware keep working after an application drops
  Fortify. The aliases answer OPTIONS only: a second GET route on the same URI
  would replace the package's and erase its `base-tenant.*` name.
- `Base\Tenant\Support\Home` and `Base\Tenant\Support\RouteGroup`.
- **Outbound webhooks are configurable.** Everything a receiver sees was a
  constant. New keys under `base-tenant.webhooks`, all defaulting to the 3.0
  behaviour:
  - `headers.{signature,event,delivery}` — header names.
  - `signature_prefix` — e.g. `sha256=`. The signature is still the
    HMAC-SHA256 of the raw body exactly as sent, serialised once.
  - `payload_builder` — a class implementing the new
    `Connections\Webhooks\PayloadBuilder`; `DefaultPayloadBuilder` keeps
    `{event, delivery, occurred_at, data}`.
  - `attempts`, `backoff`, `timeout` — the retry schedule and the HTTP timeout.
    The job's own timeout stays at least 15 s above the HTTP one.
  - `failure_limit` and `on_failure_limit` (`disable` or `degrade`).
    `degrade` keeps the endpoint receiving and sets `degraded_at`; a
    successful delivery clears it.
  - `models.endpoint`, `models.delivery` — the manager, the job and the
    relations use the configured classes, which must extend the package's.
- **`WebhookEndpointFailing` event**, raised once when an endpoint reaches the
  failure limit, whichever the action. 3.0 switched endpoints off without
  telling anyone.
- **`Webhook::redeliver($delivery)`** — a new delivery with the original's
  exact body, linked through `redelivery_of`.
- Each webhook delivery keeps the bytes it sent (`body`), so every retry and
  every redelivery sends the same signed request even if the envelope changed
  in between.
- **Strict tenancy**, all opt-in:
  - `tenancy.on_missing_tenant = throw`: a scoped query with no account in
    context and no `Tenant::runWithout()` throws `MissingTenantException`
    instead of returning nothing.
  - `tenancy.strict`: `BelongsToAccount` models get `TenantBuilder` (scoped
    `forceDelete()`, refused `truncate()`/`updateOrInsert()`/`updateFrom()`,
    `upsert()` requiring `account_id` in the conflict target and stamping it,
    account predicate in the `ON` of joins to tenant tables) and
    `TenantBelongsToMany` for pivots with `account_id`; `account_id` becomes
    immutable; updating, deleting or restoring another account's record throws
    `CrossAccountWriteException`; creating with no account throws (override
    `allowsAccountlessRecords()` to allow it); `Tenant::set($id)` with an
    unknown id throws `UnknownTenantException`.
  - `tenancy.join_exempt_tables`: tables whose `account_id` is not ownership,
    never constrained in joins or pivots.
  - `tenancy.restore_dispatch_context`: `TenantAwareBusDispatcher` pushes jobs
    returned from `runFor()`/`runWithout()` and `afterResponse()` jobs under
    the context they were built in; inline jobs restore their caller's context
    instead of resetting it.
  - All tenancy exceptions extend `Base\Tenant\Tenancy\Exceptions\TenancyException`.
- `Tenant::clear()`: drops the account and lets the next access run the
  resolver chain again.
- `TenancyBypassed` event, dispatched by `Tenant::runWithout($callback, $reason)`,
  `->acrossAccounts($reason)` and `->forAccount($other)`, with call site and
  user. Always on.
- `k2labs-base:tenancy-audit`: exits 1 when a model's table has `account_id`
  but the model does not use `BelongsToAccount`, or the reverse (paths in
  `tenancy.audit.paths`).
- `k2labs-base:prune-transfers` and `Transfer::prune($days)`: apply
  `transfer.retention_days`, which was declared and never applied. Old
  transfers go with the export and error files they produced; the source file
  of an import is never touched. Scheduled daily only when
  `transfer.prune_schedule` is on.
- Suppression drivers for Postmark (`PostmarkDriver`, basic auth in the webhook
  URL, since Postmark does not sign) and Resend (`ResendDriver`, Svix signature
  with a five-minute window). Both refuse everything until configured.
- `files.stream_fallback` (default `true`): set to `false` and `File::url()` /
  `variantUrl()` throw instead of falling back to the non-expiring streaming
  route when the disk cannot sign.
- `File::temporaryUrlOrNull()`: a signed, expiring URL or null, never the
  streaming route.
- Public file collections: `'public' => true` in `files.collections`, or
  `FileCollection::make(..., public: true)`. Their files get a stable,
  session-less address from `File::publicUrl()` (route
  `base-tenant.files.public`), checked against the collection's current rules
  on every request. Collections stay private by default.
- `FileCollection::fromConfig()`, `File::isPublic()`, `File::collectionRules()`.

### Changed

- Registration runs user, account and owner role in one transaction, and
  `Illuminate\Auth\Events\Registered` is dispatched after the commit instead of
  before.
- `ActivityLog` may be created with no account under `tenancy.strict`
  (sign-in and session events belong to a person, not an account).
- The suppression webhook answers 401 instead of 404 for `postmark` and
  `resend` until their secrets are configured.

### Deprecated

- `OutboundWebhook::FAILURE_LIMIT` and `OutboundWebhookDelivery::BACKOFF`.
  Read `WebhookManager::failureLimit()`, `backoff()` and `attempts()`. The
  first entry of `BACKOFF` (60) never applied: the 3.0 schedule was five
  attempts at 5m, 30m, 2h and 12h, which is the new default.

### Fixed

- **`layouts.guest` was never read.** The auth screens fixed their layout with
  `#[Layout('base-tenant::layouts.guest')]`. They now use `#[GuestLayout]`,
  which reads the config when the screen renders.
- **`home_url` was ignored on half the screens.** Login, second factor, password
  confirmation, email verification, forced password change, registration,
  social sign-in, invitations, account switching and impersonation named
  `base-tenant.dashboard` by hand. Every one of them goes through
  `Home::url()` now, which accepts a route name or a path.
- **Registration returned 500 without subscriptions.** It always redirected to
  `base-tenant.checkout`, a route that only exists with subscriptions on — after
  the user and their account had been created. It goes to the checkout only
  when that route exists, and home otherwise.
- **Registration could leave half an account.** A missing owner role (roles
  never synced) now rolls everything back instead of leaving an account nobody
  can manage.
- **Users coming from Fortify were locked out of their recovery codes.** Fortify
  stores them as `encrypt(json_encode($codes))`; the `array` cast read that as
  null, and spending a code failed with a `TypeError`. Both formats are read,
  and a row keeps its format when a code is spent or the set regenerated.
- **`Tenant::set(null)` left the manager marked as resolved**, so in a
  long-lived process (Octane, tests) the next request never resolved its
  account. Use `Tenant::clear()`; under `tenancy.strict`, `set(null)` now
  behaves like it.

## [3.0.3] - 2026-09-22

Ten defects, nine of them found by auditing a real application that had taken
ownership of the code. Eight only bite an application that has scaffolded or
ejected; two bite every installation.

### Fixed

- **`k2labs-base:make-module` broke `config/base-tenant.php`.** The generator
  looked for a `// base-tenant:permissions` anchor that the published config
  never had, and fell back to appending the block at the end of the file —
  after the `];` that closes the array. The result is a parse error: the whole
  application stops booting, and the command reports a successful edit. The
  anchor now ships in the config, and a missing anchor stops the generator
  before it writes anything. This one affects every installation.
- **`/upgrade` returned 500 without subscriptions.** The screen `HasFeature`
  redirects to when a plan lacks a feature linked to `base-tenant.billing`,
  a route that only exists when subscriptions are enabled. The link is behind
  `Route::has()` now, with a contact line in its place. Every installation that
  sells its plans outside the product hit this.
- **The activity screen returned 500 with Flux Pro installed.** Its date-range
  filter resolves the Pro component at runtime — right, since compiling
  `<flux:date-picker>` would break `view:cache` where Pro is absent — but named
  it `flux:date-picker`. `x-dynamic-component` resolves a view name, so it takes
  `flux::date-picker`, with the namespace separator. One character, one screen.
- **The generated service provider was missing eight registrations:** the
  `DnsLookup` and `LangFileWriter` container entries, which cannot be autowired,
  and the schedule, email verification, social providers, suppression guard,
  invitation listener and security policy lifecycle. An ejected application
  booted, served its routes, and failed only where one of them was needed —
  `/domains`, `/languages`, verification mail, retention jobs. A test now
  compares the two providers call by call.
- **Scheduled work ran twice, or not at all.** The package registered the
  schedule before the guard that hands the application its own code, so it kept
  scheduling alongside a scaffolded application's provider. The table moved to
  `Support\ScheduledTasks`, which travels with the code, and the package stands
  down once the application owns it.
- **The module generator did not survive the eject.** `Console\Support\ModuleField`
  was excluded from the copy although `MakeModuleCommand` imports it, and the
  templates it reads were never copied either — the fallback path resolves to
  the project root once the command lives in `app/`. Both travel now, the
  templates rewritten on the way, and a missing template says so instead of
  failing on an unreadable path.
- **The tests the generator writes now pass.** They called four helpers that
  only existed inside the package's own suite; those fixtures ship with
  `TenancyAssertions`, which host applications already autoload. They also
  refresh the database and grant the new module's permissions, which no role
  knows about until you add them.
- **Eject left the application's own references to the package broken.** It
  rewrites what it copies; a seeder, test or job that imported `Base\Tenant\…`
  kept importing a class that no longer exists. Those files are rewritten too
  now, before the package goes, and every one is listed.
- **Eject could leave `composer.json` unreadable.** Composer accepts two shapes
  for `repositories` — a map and a list — and merging the package's map into an
  application's list renumbered the keys into an object with a `"0"` in it.
  Composer then refuses to read the file at all, which takes the eject's own
  `composer remove` with it: the package stays installed and its provider keeps
  booting beside the copied code.
- **Namespaced translations moved to `lang/vendor/tenant/`,** where Laravel keeps
  them. Under `lang/tenant/` they load, but every tool that lists locales by
  reading `lang/` counts `tenant` as a language stuck at 0% coverage.

### Fixed — PostgreSQL

Three more of the same kind: correct on SQLite and MySQL, broken on PostgreSQL,
and invisible until the suite ran against a real server.

- **Every search box was case-sensitive.** `LIKE` ignores case on MySQL and
  SQLite and respects it on PostgreSQL, so typing `alf` stopped finding `Alfa`
  in the account switcher, the user, invitation, file, transfer and activity
  tables and the notification list. They ask `Support\Search::operator()` for
  the comparison now, which is `ILIKE` on PostgreSQL.
- **The number separators came back padded.** They were `char()` without a
  length — `char(255)` — and PostgreSQL pads a CHAR to its full width, so the
  comma a user picked arrived as a comma followed by 254 spaces and every
  amount on the screen was formatted with it. The columns are one character
  wide, with a migration that trims what is stored.
- **Filter values from the request reached typed columns unchecked.** A
  `causer_id` that is not a uuid, or a date filter that is not a date, is an
  error on PostgreSQL rather than an empty result: a 500 where the other
  engines return nothing. The activity filters and
  `k2labs-base:export-user-data` check the shape of the value before asking.

The suite itself now passes on PostgreSQL 18 and MySQL 8.4 as well as SQLite.

## [3.0.2] - 2026-09-21

### Fixed

- `user_invites.token` was created as `varchar(50)` while invitation tokens are
  64 characters long. SQLite ignores the length, so it went unnoticed;
  PostgreSQL rejects the insert and MySQL truncates it, breaking invitations in
  both. The column is created as `varchar(64)` and a migration widens it on
  existing installations, keeping the data and the unique index.
- `usage_events.subject` was declared with `nullableMorphs()`, so `subject_id`
  was a `bigint` receiving the UUID keys the package uses everywhere. On
  PostgreSQL and MySQL every metered write that names its subject failed —
  uploading a file, running an import or an export. It is `nullableUuidMorphs()`
  now, with a migration that converts the column on existing installations.
- `k2labs-base:install` could not get past its own database question on a
  project whose SQLite file did not exist yet: connecting to a missing file
  fails, and the file was only created once the settings were applied — which
  needed a working connection. It now offers to create the file it just asked
  for, and stops retrying instead of looping when nobody can answer
  (`--no-interaction`).

### Changed

- The test suite reads the connection from the environment. It still defaults
  to in-memory SQLite, and `DB_CONNECTION=pgsql vendor/bin/pest` now runs the
  same suite against a real server — which is what catches schema problems
  SQLite forgives.

## [3.0.1] - 2026-09-20

### Fixed

- The starter kit is installed as `k2labs/starter-kit`; the documentation still
  called it `k2/base-tenant-kit`, a name that does not resolve on Packagist.

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
