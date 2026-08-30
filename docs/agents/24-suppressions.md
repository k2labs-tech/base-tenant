# Email suppressions

**Module:** Q5 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_SUPPRESSIONS_ENABLED` (on by default)

## When to use this

Nothing, most of the time. The guard is global and already running — this
document exists so you do not build a second one.

Reach for the API directly only to suppress an address by hand, to release one,
or to add a provider.

## When NOT to use this

- Marketing preferences. "Does not want the newsletter" is a setting on the
  user; this list is "must not be written to at all".
- Per-account blocking. A hard bounce is a fact about the address, not about
  the customer who triggered it, and the list is deliberately global.

---

## The guard

A `MessageSending` listener cancels any message addressed to a suppressed
address — the package's own mail, the application's, and anything a library
sends. On the event and not inside a Mailable, because a guard that has to be
remembered at each call site will be forgotten at one of them.

Cancelled sends are logged. A message silently not arriving is the hardest kind
of support ticket: nobody knows whether it went.

---

## API

```php
use Base\Tenant\Facades\Suppression;

Suppression::isSuppressed($email);
Suppression::suppress($email, 'manual', 'ui', ['note' => 'Asked by phone']);
Suppression::release($email);       // manual only, on purpose
```

Reasons: `bounce`, `complaint`, `manual`, `unsubscribe`.

Addresses are stored lower-cased and unique. Comparing case-sensitively lets
`Ada@Example.test` past a guard holding `ada@example.test`.

Nothing comes off the list on its own. An address that recovers automatically
puts the sending reputation at the mercy of whatever decided it had.

---

## Providers

`POST /webhooks/suppressions/{driver}`, with `mailgun` shipped.

Mailgun's signature is `hmac_sha256(timestamp + token, signing_key)`, checked
inside a five-minute window: without one, a captured request stays valid
forever and can be replayed to suppress any address.

Only permanent failures, complaints and unsubscribes suppress. A temporary
failure is a mailbox that was full this morning, and suppressing on it loses
customers to a full inbox.

Add a provider by implementing `SuppressionDriver` and adding a config line.

---

## Importing

```bash
php artisan k2labs-base:import-suppressions bounces.csv --column=email
```

For the first day on a new installation: whatever the previous provider had
suppressed has to come across, or the reputation that was earned is thrown away
on the first send. Unusable rows are counted rather than fatal — a file
exported by hand is full of blanks.

---

## Anti-patterns

```php
// ✗ A check at one call site. The other forty send anyway.
if (! EmailSuppression::where('email', $user->email)->exists()) {
    Mail::to($user)->send(new Invoice);
}

// ✓ Already handled. Just send.
Mail::to($user)->send(new Invoice);
```
