# Architecture — tenancy

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-12

## When to use this

Any time a model, a query, a job or a cache key touches data belonging to a
customer.

## When NOT to use this

Rows that belong to the installation rather than to a customer — the language
catalogue, the email suppression list, platform staff users. Scoping those to
an account would make them invisible to the console, where there is no account
in context.

---

## The account in context

One account is "current" for the duration of a request or a job. Everything
tenant-scoped reads from it.

```php
use Base\Tenant\Facades\Tenant;

Tenant::current();      // ?Account
Tenant::currentId();    // ?string
Tenant::set($account);  // put an account in context
Tenant::forget();       // clear it

// Run a closure as another tenant, then restore whatever was there before.
Tenant::runFor($account, fn () => Invoice::create([...]));
```

`runFor()` restores the previous context even if the closure throws. Setting
context by hand and forgetting to restore it leaks one customer's context into
the next iteration of a loop, which is how one account's data ends up written
against another.

### How the account is resolved

Resolvers run in order until one answers, configured in
`base-tenant.tenancy.resolvers`:

1. `DomainTenantResolver` — the request host, for per-domain installs
2. `ApiTokenTenantResolver` — the Sanctum token's `account_id`
3. `SessionTenantResolver` — the account the user switched to
4. `UserTenantResolver` — the user's primary account

### When no account resolves

`base-tenant.tenancy.on_missing_tenant` decides:

- `auto` (default) — unfiltered in console and queue work, no results over HTTP
- `allow` — unfiltered everywhere (single-tenant installs only)
- `deny` — no results anywhere without an account

---

## BelongsToAccount

```php
use Base\Tenant\Traits\BelongsToAccount;

class Booking extends Model
{
    use BelongsToAccount;
}
```

That is the whole integration. It adds a global scope filtering by
`account_id`, and stamps `account_id` on create from the account in context.

```php
Booking::all();                        // this account's bookings
Booking::query()->acrossAccounts();    // every account — superadmin tooling only
Booking::query()->forAccount($other);  // one specific account
$booking->account;                     // the owning Account
```

Use a different column with `public const ACCOUNT_ID = 'company_id';`.

### Anti-patterns

```php
// ✗ The scope already does this, and this version forgets the console,
//   forgets queued jobs, and is one missed call away from a leak.
Booking::where('account_id', session('account_id'))->get();

// ✗ Bypasses the scope on a query that had no reason to.
Booking::withoutGlobalScopes()->get();

// ✓
Booking::all();
```

---

## Jobs, cache and broadcasting

**Jobs carry the tenant automatically.** `QueueTenancy` records the account
when a job is dispatched and restores it before `handle()` runs. Do not put
`account_id` in the constructor and re-set it — you would be maintaining a
second mechanism that can disagree with the first.

```php
// ✓ The scope resolves the same account it was dispatched from.
class SendReminder implements ShouldQueue
{
    public function __construct(public string $bookingId) {}

    public function handle(): void
    {
        $booking = Booking::find($this->bookingId);
    }
}
```

**Cache keys must be namespaced by tenant.** A key without the account in it
serves one customer's data to another.

```php
Tenant::cacheKey('report:monthly');   // → 'tenant:{account}:report:monthly'
```

---

## Permissions and tenancy

Roles are held *within an account*. The same user can be an administrator in
one account and a viewer in another, so a role check always means "in the
account in context".

`spatie/laravel-permission` is configured with `account_id` as its team key and
`TenantTeamResolver` as its resolver; that wiring happens in the package's
service provider and needs nothing from the host.

**Trap:** role rows store the *morph class* of the user model. If the host
replaces the user model, `config('base-tenant.models.user')` is what must be
queried — not `Base\Tenant\Models\User`. Querying the package class produces
an empty roles list in every real install, and no test in the package suite
catches it because there the configured model *is* the package model.

```php
// ✓
$model = config('base-tenant.models.user', User::class);
$model::query()->with('roles')->get();
```

---

## Testing tenancy

Every module ships at least an isolation test. The shape:

```php
test('one account cannot see another account\'s bookings', function () {
    $mine = $this->createAccount();
    $theirs = $this->createAccount();

    Tenant::runFor($theirs, fn () => Booking::create(['reference' => 'secret']));

    $this->actingAsTenant($this->createUser($mine), $mine);

    expect(Booking::count())->toBe(0);
});
```

Helpers available on the test case: `createAccount()`, `createUser()`,
`actingAsTenant()`, `syncPermissions()`.
