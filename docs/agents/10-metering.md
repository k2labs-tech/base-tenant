# Metering — usage and plan limits

**Module:** M1 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_METERING_ENABLED` (on by default)

## When to use this

- Counting anything an account consumes: API calls, generated documents,
  stored bytes, messages sent.
- Enforcing a numeric plan limit.
- Billing on usage through Stripe Billing Meters.

## When NOT to use this

- A count that is cheap and exact from a table you already have and that
  nothing caps — `$account->users()->count()` for a screen is fine.
- Anything not per account. Metering is tenant-scoped by construction.

---

## Declare the metric first

Metering refuses keys that are not declared. A typo would otherwise open a
counter that nothing caps, nothing shows and nobody notices — the account
would sail past its limit with the meter reading zero.

```php
// config/base-tenant.php
'metering' => [
    'metrics' => [
        'documents.generated' => [
            'type' => 'counter',        // counter | gauge
            'reset' => 'month',         // none | day | month | year
            'feature' => 'max_documents',
            'stripe_meter' => 'documents',
        ],
        'storage.bytes' => [
            'type' => 'gauge',
            'feature' => 'max_storage_gb',
            'scale' => 1073741824,      // metric units per feature unit
        ],
    ],
],
```

**counter vs gauge.** A counter accumulates over its period and resets — API
calls this month. A gauge is a level that goes up and down and never resets —
bytes on disk. The default `reset` follows the type: `month` for a counter,
`none` for a gauge.

**scale.** The feature is written in the unit that reads well on a pricing page
(gigabytes); the metric counts the unit the code has to hand (bytes). `scale`
bridges them. Without it an account would be allowed a gigabyte for every byte
it is owed.

---

## API

```php
use Base\Tenant\Facades\Meter;

Meter::increment('documents.generated');              // returns the new total
Meter::increment('documents.generated', 5, [
    'subject' => $document,                           // what caused it
    'metadata' => ['template' => 'invoice'],
]);
Meter::decrement('storage.bytes', $file->size);
Meter::set('storage.bytes', $recalculated);           // exact value

Meter::current('documents.generated');                // int
Meter::limit('documents.generated');                  // -1 = unlimited
Meter::remaining('documents.generated');              // -1 = unlimited
Meter::percentage('documents.generated');             // ?int, null when uncapped
Meter::wouldExceed('documents.generated', 3);         // bool
Meter::history('documents.generated', 12);            // ['2026-01' => 40, ...]
Meter::summary();                                     // every metric, for a screen

Meter::incrementOrFail('documents.generated');        // throws when full
Meter::for($account)->current('storage.bytes');       // a specific account
```

### increment vs incrementOrFail

`increment()` is a lock-free atomic add: fast, and it does not enforce
anything. `incrementOrFail()` takes a row lock, checks against the limit and
either consumes or throws `UsageLimitExceededException`.

Use `incrementOrFail()` wherever the limit is meant to actually stop the work.
A limit enforced from an unlocked reading is not a limit: two requests arriving
together are both told there is room for the last unit.

`UsageLimitExceededException` renders itself as **402 Payment Required** — a
JSON body for API clients, a page with an upgrade call to action for browsers.

---

## Gating a route

```php
Route::post('/documents', GenerateDocument::class)
    ->middleware('base-tenant.metered:documents.generated,1');
```

The middleware **takes the unit before the work runs**, and gives it back if
the response is 4xx/5xx or the handler throws. Checking first and charging
later is two statements with a gap in between, and two requests arriving in
that gap both pass.

In Blade: `@withinlimit('documents.generated') ... @endwithinlimit`.

---

## Feature integration

When a plan feature caps a declared metric, `Feature::withinLimit()` reads the
meter itself and compares in the metric's unit:

```php
Feature::withinLimit('max_storage_gb');     // no usage argument needed
Feature::withinLimit('max_users', $count);  // not metered: pass it
```

Calling it with neither a metric nor a usage figure throws, rather than
returning `true` — an unknown answer must not read as permission.

---

## Warnings

Crossing 80% or 100% of an allowance notifies the account owner, once per level
per period. Dropping back below re-arms the warning without sending anything:
nobody needs an email to say their usage went down.

---

## Billing

`k2labs-base:report-usage` runs hourly and sends unreported deltas to Stripe
Billing Meters, for metrics that declare a `stripe_meter`. Metrics without one
never leave the database.

The batch is claimed before it is sent and released if the send fails, so a run
that dies half way repeats nothing and loses nothing. Stripe also receives an
identifier derived from the batch, which makes a retry a no-op on their side.

---

## Anti-patterns

```php
// ✗ A count that grows with the table, run on every request, and that no
//   limit can be enforced against atomically.
if (Document::count() < $account->planLimit('max_documents')) { ... }

// ✗ Check then act: two requests in the gap both pass.
if (! Meter::wouldExceed('documents.generated')) {
    Meter::increment('documents.generated');
}

// ✓
Meter::incrementOrFail('documents.generated');
```

```php
// ✗ Comparing bytes against gigabytes.
if (Meter::current('storage.bytes') < Feature::limit('max_storage_gb')) { ... }

// ✓ `limit()` already returns the metric's unit.
if (! Meter::wouldExceed('storage.bytes', $size)) { ... }
```

---

## Tables

- `usage_counters` — one running total per account, metric and period.
  `UNIQUE (account_id, metric, period)`. `period` is `''` and never null for
  metrics that do not reset: a unique index treats two nulls as distinct rows.
- `usage_events` — the signed trail of every movement, and the queue Stripe is
  fed from. The events always sum to the counter.
