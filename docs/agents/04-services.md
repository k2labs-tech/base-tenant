# Services and middleware

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-18

## When to use this

- You need one of the package's service classes from application code:
  entitlements, settings, activity, invitations, account deletion, roles,
  notifications.
- You are attaching package middleware to a route and need to know exactly
  what it does when it refuses.

## When NOT to use this

- Reading or writing plan features and settings from a screen. Use the
  `Feature` and `Settings` facades — see [02](02-conventions.md). The services
  below are the layer underneath them.
- Anything metering-related. `Meter` and `base-tenant.metered` are documented
  in [10](10-metering.md); only the middleware's refusal behaviour is repeated
  here.

---

## Services at a glance

Every public method on every one of these is **static**. There is nothing to
resolve from the container and nothing to inject.

| Service | Normally reached through | Call it directly when |
|---|---|---|
| `FeatureService` | `Feature` facade | You need the *plan* value with overrides ignored |
| `SettingService` | `Settings` facade, `tenant_setting()` | Never, in new code — see below |
| `ActivityLogService` | `LogsActivity` trait | You are logging something that is not a model write |
| `InvitationService` | — | Always; there is no facade |
| `AccountDeletionService` | — | Always; there is no facade |
| `PermissionRegistry` | `k2labs-base:sync-roles` | You are syncing from a seeder or a test |
| `NotificationService` | — | See the caveat; it is thin and untyped |

All of them live in `Base\Tenant\Services`.

---

## FeatureService

Resolves what an account is entitled to. Two layers, innermost wins: the plan
in `config('base-tenant.plans')`, then per-account overrides in the `features`
table.

```php
FeatureService::getPlanForAccount(Account $account): string
FeatureService::getPlanConfig(string $planKey): ?array
FeatureService::getAccountPlanName(Account $account): string

// Plan only — overrides ignored.
FeatureService::planValue(?Account $account, string $feature): mixed
FeatureService::planAllows(Account $account, string $feature): bool
FeatureService::planLimit(Account $account, string $feature): int
FeatureService::planFeatures(Account $account): array

// Plan + overrides. These are what the `Feature` facade calls.
FeatureService::accountCan(Account $account, string $feature): bool
FeatureService::getLimit(Account $account, string $feature): int
FeatureService::isWithinLimit(Account $account, string $feature, int $currentUsage): bool
FeatureService::getAccountFeatures(Account $account): array

FeatureService::overridesFor(Account $account): array
FeatureService::flush(?Account $account = null): void
```

`getPlanForAccount()` matches the active subscription's `stripe_price` against
each plan's `stripe_price_id`, falling back to `'free'`. With
`base-tenant.subscription.enabled` off it returns
`base-tenant.subscription.default_plan` without touching the database.

**Use the facade, not this.** `Feature::active()` / `limit()` / `withinLimit()`
resolve the account for you and read the meter for metered limits;
`FeatureService::isWithinLimit()` will not — it compares against the number you
pass and nothing else. The one thing the facade cannot do is show the plan
*underneath* an override, which is what an entitlements screen has to show:

```php
// "Starter allows 5. This account is on 25 until 30 September."
$planValue = FeatureService::planValue($account, 'max_users');   // 5
$effective = Feature::for($account)->limit('max_users');         // 25
```

`overridesFor()` is memoised per account for the life of the request. Anything
that writes to the `features` table outside `FeatureSet::set()` must call
`FeatureService::flush($account)` or the request keeps serving the old answer.

---

## SettingService

The **legacy** key–value reader, over the `settings` table written by the
`HasSettings` trait.

```php
SettingService::get(string $key, mixed $default = null, ?User $user = null, ?Account $account = null): mixed
SettingService::resolve(string $key, mixed $default = null): mixed
```

`get()` reads the user first, then the account, then the default; a stored
`null` is indistinguishable from absent and falls through. `resolve()` is
`get()` with the authenticated user and the account in context filled in, and
is what the `tenant_setting()` helper calls.

```php
tenant_setting('timezone', 'UTC');
SettingService::get('timezone', 'UTC', account: $account);  // a specific account
```

**Do not reach for this in new code.** Typed settings — `Settings::register()`
and a `Settings` subclass, see [02](02-conventions.md) — give you validation,
defaults, an editor screen and history. `SettingService` has no schema, no
group argument and no cache: every call is a query.

---

## ActivityLogService

```php
ActivityLogService::log(
    ?Model $subject = null,
    string $action = 'custom',
    ?array $oldValues = null,
    ?array $newValues = null,
    ?string $description = null,
): ActivityLog

ActivityLogService::filterSensitive(array $values): array
ActivityLogService::getForAccount(
    string $accountId,
    ?string $action = null,
    ?string $causerId = null,
    int $perPage = 25,
)
ActivityLogService::pruneOlderThan(int $days = 90): int
```

**There is no causer or account argument.** The causer is `Auth::user()` and
the account is `Tenant::currentId()`, both read at the moment of the call. IP
and user agent come from the request. Logging from a job or a command with no
tenant in context therefore writes a row with a null account — wrap it:

```php
Tenant::runFor($account, fn () => ActivityLogService::log(
    $booking,
    'exported',
    description: __('app::activity.booking_exported'),
));
```

`old`/`new` go under `properties` through `filterSensitive()`, which drops
exactly `password`, `remember_token`, `two_factor_secret` and
`two_factor_recovery_codes`. Nothing else — a model holding an API token in
another column has it written to the log verbatim.

Model writes are already logged by the `LogsActivity` trait. Call `log()`
directly only for what is not a model write: exports, sign-ins, a switch.

---

## InvitationService

```php
InvitationService::send(string $email, string $accountId, string $roleId, User $inviter): UserInvite
InvitationService::resend(UserInvite $invite): void
InvitationService::accept(UserInvite $invite, User $user): void
InvitationService::revoke(UserInvite $invite): void
InvitationService::getPendingForAccount(string $accountId)
```

```php
$invite = InvitationService::send($this->email, $account->getKey(), $this->selectedRole, auth()->user());
```

`send()` deletes any unaccepted invite for the same address in the same account
first, so re-inviting never leaves two live tokens. The token is
`Str::random(64)`, expiry is `base-tenant.invitations.expires_in_days` (7), and
the mail is sent on demand — the invitee is not a user yet.

`accept()` marks the invite, attaches the user to the account, sets
`account_id` if the user had none, and assigns the role **inside
`Tenant::runFor()`** — roles are per account, so assigning one outside the
account's context binds it to the wrong tenant or to none. `revoke()` deletes
the row; there is no revoked state to query for.
`accept()` also refuses another address (`InvitationException`) or one the
security policy bars (`DomainNotAllowedException`, as does `resend()`); a link
is kept by `remember()`, read back by `remembered()` (the register screen uses it
to join the account instead of founding one) and finished on login by
`AcceptPendingInvitationListener` through `acceptPending()`, silent on refusal.

---

## AccountDeletionService

Answers whether an account can be deleted without orphaning rows, by asking the
schema builder rather than a hard-coded list.

```php
AccountDeletionService::canDelete(Account $account): bool
AccountDeletionService::hasUsers(Account $account): bool
AccountDeletionService::blockingTables(Account $account): array   // table names
AccountDeletionService::tablesWithAccountId(): array
AccountDeletionService::flushSchemaCache(): void
```

```php
$blocking = AccountDeletionService::blockingTables($account);

if ($blocking !== []) {
    $this->error = __('base-tenant::accounts.delete_has_data', [
        'tables' => implode(', ', $blocking),
    ]);

    return;
}
```

A new module's table is covered the moment it has an `account_id` column —
nothing to register. Tables that model the account itself (`accounts`,
`account_user`, `users`, `roles`, the queue and cache tables, …) are excluded,
and `config('base-tenant.account_deletion.ignore_tables', [])` adds to that
list — the key is not in the published config, add it if you need it.
`tablesWithAccountId()` is cached in a static; `flushSchemaCache()` exists for
tests that create tables after the first scan.

---

## PermissionRegistry

```php
PermissionRegistry::sync(): array   // ['permissions' => int, 'roles' => int]
```

Writes `config('base-tenant.permissions')` and the `system`, `customer` and
`custom` role groups to the database, expands `'bookings.*'` and `'*'` against
the configured catalogue, and forgets Spatie's permission cache. Roles it
creates are global (`account_id` null).

`k2labs-base:sync-roles` is this and nothing else; call it directly from a
seeder or a test bootstrap. It is `updateOrCreate` throughout, so repeating it
is safe, and it never deletes: a permission removed from config stays in the
database until you remove it yourself.

---

## NotificationService

Thin, untyped helpers over Laravel's notification facade.

```php
NotificationService::notifyProjectUsers($project, $notification, $causer): void
NotificationService::notifySpecificUsers(array $users, $notification): void
NotificationService::getUnreadCount($user): int
NotificationService::markAsRead($user, string $notificationId): void
NotificationService::markAllAsRead($user): void
```

**Caveat.** `notifyProjectUsers()` is application-shaped, not package-shaped:
it calls `$project->getAllMembers()` — a method the package does not define —
and excludes `$causer->id`, which must not be null. It works only if your model
implements that method. `notifySpecificUsers()` is typed `array`, so a
`Collection` is a `TypeError`; pass `->all()`.

---

## Middleware

Eight aliases, registered in `BaseTenantServiceProvider::middlewareAliases()`.

### Pushed onto the `web` group for you

You do not attach these, and you cannot remove them from a route by omission —
only by editing the group.

| Alias | Pushed when |
|---|---|
| `base-tenant.account-context` | Always |
| `base-tenant.password-changed` | `base-tenant.force_password_change.enabled` is true |
| `base-tenant.terms` | The GDPR module is on **and** `base-tenant.gdpr.terms_version` is set |

**`base-tenant.account-context`** — `SetAccountContext`. Writes the current
account's key to the session as `current_account_id` so the choice survives
navigation. Never refuses, never redirects; a request with no account in
context or no session passes untouched.

**`base-tenant.password-changed`** — `EnsurePasswordChanged`. When the feature
is on and the authenticated user has `must_change_password`, everything
redirects to `base-tenant.password.change` with a `warning` flash. Through the
gate go: that route, a route named `logout`, any `livewire/*` path, and any
request carrying an `X-Livewire` header.

> Trap: the allow list contains `'logout'`, but the package's own logout route
> is named `base-tenant.logout`. It passes today only because it is reached
> from a Livewire request. Name your application's logout route `logout` if you
> add one.

**`base-tenant.terms`** — `EnsureTermsAccepted`. Compares
`$user->terms_version` against `base-tenant.gdpr.terms_version`. On a mismatch:
`403` with `{message, terms_version}` for a JSON request, otherwise a redirect
to `base-tenant.terms` — and if that route does not exist, the request is let
through rather than looped. `base-tenant.terms`, `base-tenant.logout` and
`base-tenant.social.*` are always reachable, or the user would be locked in
with no way to accept and no way out. See [25](25-gdpr.md).

### Attach yourself

**`base-tenant.locale`** — `SetLocale`. Takes the locale from the user's
`locale`, then the session, then the default, each checked against the
languages actually enabled; sets the fallback to the default language rather
than `app.fallback_locale`; writes the result back to the session. Never
refuses. Not in the `web` group — attach it yourself. See [20](20-languages.md).

```php
Route::middleware(['web', 'base-tenant.locale'])->group(base_path('routes/app.php'));
```

**`base-tenant.subscription`** — `HasSubscription`. Requires
`$user->account->hasActiveSubscription()`. Refuses by redirecting to
`base-tenant.checkout`. Bypassed entirely when `base-tenant.subscription.enabled`
is false, when the user has `is_admin`, and outside production when Stripe is
not fully configured (`cashier.key`, `cashier.secret`,
`base-tenant.subscription.default_product`, `default_price`).

```php
Route::middleware(['auth', 'base-tenant.subscription'])
    ->get('/reports', ReportsIndex::class);
```

**`base-tenant.no-subscription`** — `DoesNotHaveSubscription`. The mirror, for
the checkout and pricing screens: it redirects to
`config('base-tenant.home_url', 'base-tenant.dashboard')` when the account
already subscribes, when the user is an admin, or outside production without
Stripe configured. Note it does **not** consult
`base-tenant.subscription.enabled`.

```php
Route::middleware(['auth', 'base-tenant.no-subscription'])
    ->get('/checkout', Checkout::class);
```

**`base-tenant.feature:{feature}`** — `HasFeature`. With no account in context
it aborts `403` with `base-tenant::plans.no_account`. With the feature
inactive: `403` `base-tenant::plans.feature_not_available` for a JSON request,
otherwise a redirect to `base-tenant.upgrade`. Overrides count — it goes
through `FeatureService::accountCan()`.

```php
Route::get('/api-keys', ApiKeys::class)->middleware('base-tenant.feature:api_access');
```

**`base-tenant.metered:{metric},{amount}`** — `EnsureWithinUsageLimit`. Takes
the units *before* the work runs and refunds them if the handler throws or the
response is 4xx/5xx. Over the limit it throws `UsageLimitExceededException`,
which renders as **402**. A no-op when the metering module is off. `amount`
defaults to 1. Full contract in [10](10-metering.md).

```php
Route::post('/documents', GenerateDocument::class)
    ->middleware('base-tenant.metered:documents.generated,1');
```

---

## Anti-patterns

```php
// ✗ There is no causer or account argument. The fourth argument is $newValues,
//   so this writes the description into the diff and loses it.
ActivityLogService::log($booking, 'exported', 'Booking exported', $user->id);

// ✓ Named arguments, and a tenant in context.
Tenant::runFor($account, fn () => ActivityLogService::log(
    $booking,
    'exported',
    description: __('app::activity.booking_exported'),
));
```

```php
// ✗ Ignores per-account overrides: a customer on a pilot limit is told no.
if (FeatureService::planLimit($account, 'max_users') > $count) { ... }

// ✓ Effective entitlement, overrides included.
if (Feature::for($account)->withinLimit('max_users', $count)) { ... }
```

```php
// ✗ Assigns a role with no account in context — it binds to the wrong tenant.
$user->assignRole($invite->role);
$invite->update(['accepted_at' => now()]);

// ✓ accept() already does the attach, the role and the context, in order.
InvitationService::accept($invite, $user);
```

