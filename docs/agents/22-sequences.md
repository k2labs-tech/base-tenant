# Sequences — correlative numbering

**Module:** Q3 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_SEQUENCES_ENABLED` (on by default)

## When to use this

Any number a human reads and expects to be consecutive: booking references,
invoice numbers, case files, order numbers.

## When NOT to use this

- Primary keys. Those are UUIDs here, on purpose.
- Anything where gaps do not matter and uniqueness is enough — use a UUID.

---

## API

```php
use Base\Tenant\Facades\Sequence;

Sequence::next('bookings');                                   // "00001"
Sequence::next('invoices', format: 'F{year}-{number:5}', period: 'year');
Sequence::peek('bookings');                                   // without consuming
Sequence::current('bookings');                                // the raw counter
Sequence::setNext('invoices', 4312);                          // migrating an old system
```

Every call is scoped to the account in context. Pass `account:` for a specific
one, or `account: Sequence::GLOBAL` for a counter shared by the whole
installation.

### Format tokens

| Token | Result |
|---|---|
| `{number}` | `42` |
| `{number:5}` | `00042` |
| `{year}` `{month}` `{day}` | from the period, not the clock |

The default is `{number:5}`.

The date comes from the period the number belongs to, not from `now()`: a
number taken from the 2026 counter at 23:59:59 on 31 December prints 2026, not
2027.

### Periods

`year`, `month`, `day`, `none` (the default). The counter restarts on its own
when the period rolls over — nothing has to run on the first of January.

---

## Guarantees

The number is handed out inside a transaction holding a lock on the row, so
two requests arriving together get different numbers. `max(id) + 1` and an
exposed autoincrement both look like they work until they do not, and a
duplicated invoice number is found by an auditor rather than by a test.

Do not store what `peek()` returns. By the time the record is saved, somebody
else may have taken it.

---

## Anti-patterns

```php
// ✗ Two requests read the same max and write the same reference.
$next = Booking::max('reference') + 1;

// ✗ Exposes the primary key, leaks how many records exist, and cannot restart.
$reference = $booking->id;

// ✓
$reference = Sequence::next('bookings', format: 'B{year}-{number:5}', period: 'year');
```

---

## Table

`sequences` — `account_id`, `key`, `period`, `next_value`, `format`.
`UNIQUE (account_id, key, period)`.

`account_id` is a string holding either an account id or the literal `global`,
and `period` is `''` rather than null when the sequence does not reset: a
unique index treats two NULLs as distinct, so a nullable column would allow two
counters for the same key and the numbering would silently fork.
