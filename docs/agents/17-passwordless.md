# Passwordless — signing in without a password

**Module:** passwordless · **Package version:** v3 · **Last reviewed:** 2026-09-01
**Switch:** `BASE_TENANT_PASSWORDLESS_ENABLED` (on by default)

## When to use this

When a product wants sign-in that does not start with a password: the password
is the first friction of a sign-up and the first cause of support tickets.

## When NOT to use this

- As a way around the second factor. It is not one, and the code makes sure of
  it — see below.
- For API access. That is a token, not a session — see [18](18-api.md).

---

## API

```php
use Base\Tenant\Facades\MagicLink;

MagicLink::enabled();
MagicLink::request($email, request());     // sends, or silently does nothing
MagicLink::tooManyRequests($email, request());
MagicLink::recordRequest($email, request());
MagicLink::consume($token);                // returns the User, or null
MagicLink::prune(7);
```

| Route | |
|---|---|
| `base-tenant.magic-link.request` | The form. `base-tenant.auth.request-magic-link` |
| `base-tenant.magic-link.consume` | Spends the token. Throttled at 10/min |

---

## The three rules, each with a test that fails without it

**1. A link never skips the second factor.** `MagicLinkController` checks
`hasTwoFactorEnabled()` and sends the user to the challenge instead of signing
them in. A magic link proves you hold the mailbox — which is precisely the
thing a second factor exists to cover. Signing straight in would turn every
account with 2FA into an account whose 2FA can be skipped from the inbox.

**2. A link works once.** `scopeUsable()` filters on `consumed_at`, and
`consume()` claims the row with a conditional `update` inside a transaction, so
two requests arriving with the same token in the same instant cannot both win.
Read-then-write would let both through.

**3. A link expires.** The same scope filters on `expires_at`. Default is 15
minutes, and short is the point: a link in a mailbox is a key, so the window in
which a leaked mailbox is also a live login should be measured in minutes.

---

## What the endpoint must not reveal

`request()` returns nothing whether or not the address belongs to anybody, and
the screen prints the same sentence either way. Answering differently turns the
form into a way of asking the product "is this person a customer of yours?",
which is a disclosure with no upside.

The rate limit is **per address and per IP**. Limiting only the IP lets one
person behind a large NAT lock out a whole office; limiting only the address
lets one machine walk a list of them.

The token is stored hashed (`MagicLink::fingerprint()`). The raw value exists
only in the email. `MagicLinkNotification` is mail-only and never lands in the
database notification list, because the bell is somewhere a shoulder-surfer
can read.

---

## Passkeys

**Not built.** `base-tenant.passwordless.passkeys` does not exist yet. When it
does, it goes in this module, under this switch, and it obeys the same first
rule: a passkey used as a sign-in method still respects whatever the account's
security policy requires.

---

## Related

Everything here is subject to the account's own rules — see
[16](16-security.md). A magic link cannot be used to sidestep enforced 2FA, and
the IP allowlist applies to the session it creates like any other.

---

## Do not

| Do not | Do instead |
|---|---|
| Sign a user in straight from `consume()` | Check `hasTwoFactorEnabled()` first |
| Tell the caller whether the address exists | Return the same answer either way |
| Store or log the raw token | `MagicLink::fingerprint()` |
| Lengthen the TTL to "make support easier" | Let them request another link |
| Rate limit by IP only | Both, as `tooManyRequests()` does |
