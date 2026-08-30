# Pre-sale mode

**Module:** Q7 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_PRESALE` (**off** by default)

## When to use this

Launching a product before it is built: closed registration, a landing page, a
waiting list, and founding places with a visible counter.

## When NOT to use this

- A beta with real users. That is registration plus a feature flag.
- A marketing site. This is the application's own front door.

Off by default and staying that way, because switching it on closes standard
registration — not an effect anyone should get from upgrading a package.

---

## What switching it on does

- `GET /` serves the landing page instead of redirecting to login.
- `Register` redirects away, and refuses a direct POST as well: a form left
  standing that rejects on submit wastes the time somebody spent filling it in.
- The pricing table shows the founding plan and the seats left.

---

## API

```php
use Base\Tenant\Facades\Presale;

Presale::isOpen();
Presale::seatsLeft();
Presale::soldOut();
Presale::join($email, ['source' => 'landing']);
Presale::waiting();
```

Seats left is counted from accounts that have a Stripe customer, never from a
stored counter: the two would disagree the first time a checkout was refunded,
and the number on a landing page is a promise.

`join()` is idempotent. Signing up twice is not a mistake, and "that address is
already on the list" tells a stranger who else is on it.

---

## Components

```blade
<livewire:base-tenant.presale.waitlist-form source="landing" />
<livewire:base-tenant.presale.pricing-table />
```

The pricing table renders `config('base-tenant.plans')`, the same config the
feature gates read, so a price page cannot promise something the product does
not enforce. Useful outside pre-sale too.

---

## Opening

```bash
php artisan k2labs-base:presale-open --dry-run
php artisan k2labs-base:presale-open --batch=50
```

Each waiting person gets an empty account and an invitation into it — the
mechanism the product already has for "here is your way in". Inviting without
an account would produce an invitation into nothing.

Each one is a transaction, and `invited_at` is stamped only once the invitation
exists: a run that dies half way re-invites nobody and drops nobody. Run it
again for the next batch.

The command does **not** turn the flag off. It lives in the environment, and a
command that rewrites `.env` on a deployed machine makes a change no deploy
would reproduce — it tells you what to change instead.

---

## Not built

The founding Stripe Checkout described in the specification. `presale.price_id`
is read and the seat counter works against real customers, but the checkout
session itself is not wired up.

---

## Table

`waitlist_signups` — `email` (unique, lower-cased), `name`, `source`,
`referrer`, `metadata`, `invited_at`.
