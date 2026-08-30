# Social login

**Module:** Q2 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_SOCIAL_ENABLED` (on by default)

## When to use this

Signing in with Google, LinkedIn or Microsoft Entra ID, and linking those
identities to an existing account.

## When NOT to use this

Calling a provider's API on the user's behalf. The tokens are stored, but a
per-account integration with an external service belongs in the connections
module, not here.

---

## Configuration

A provider appears when its credentials are present in `config/services.php`.
There is no second flag: two switches for one fact is two things to get out of
step, and the failure mode — a button leading to a provider error page — reads
as a broken product.

```php
// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => '/auth/google/callback',
],
'linkedin-openid' => [ ... ],   // note the driver name
'microsoft' => [
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'redirect' => '/auth/microsoft/callback',
    'tenant' => env('MICROSOFT_TENANT', 'common'),
],
```

Restrict sign-up by email domain with `base-tenant.social.allowed_domains`.
Empty means anyone.

---

## API

```php
use Base\Tenant\Social\SocialProviders;
use Base\Tenant\Social\SocialLoginService;

SocialProviders::enabled();              // ['google', 'linkedin']
SocialProviders::configured('microsoft');
SocialProviders::label('linkedin');      // 'LinkedIn'

$social = app(SocialLoginService::class);
$social->authenticate('google', $identity);   // sign in or register
$social->link($user, 'google', $identity);    // from an authenticated session
$social->unlink($user, 'google');
$social->linkedProviders($user);
```

Routes: `GET /auth/{provider}/redirect` and `/callback`
(`base-tenant.social.*`). The same pair serves signing in and linking — which
one happens is decided by whether there is a session.

In a view: `<x-base-tenant::social-buttons />`, already on login and register.
Profile linking: `<livewire:base-tenant.profile.connected-accounts />`.

---

## The rule that matters

**An address that already has an account is never linked automatically from
the login screen.** If it were, anyone able to create an identity at the
provider with a known address would take the account over — and several
providers do not require proving the address at all.

`authenticate()` throws instead, with a message telling the person to sign in
with their password and connect the provider from their profile. Linking has
to start from a session that is already authenticated.

Other guarantees:

- `UNIQUE (provider, provider_id)` — one provider identity can never sign in
  as two different users.
- Tokens are encrypted at rest. A refresh token is a standing grant to act as
  the user, and a database dump should not be one.
- A social sign-up gets **no password**, not a random one, and arrives
  `email_verified_at` set: the provider has already proven the address.
- Disconnecting the last provider from someone with no password is refused. It
  would leave an account nobody can open, not even by resetting — a reset needs
  a password to reset.
- The second factor runs **before** the session is created. Signing in and
  challenging afterwards would leave the session existing while the challenge
  is pending, which is what the second factor is for.

---

## Anti-patterns

```php
// ✗ Account takeover. This is the exact line the module exists to prevent.
$user = User::firstOrCreate(['email' => $identity->getEmail()], [...]);
Auth::login($user);

// ✓
$user = app(SocialLoginService::class)->authenticate('google', $identity);
```

```php
// ✗ A second source of truth about which providers are on.
if (config('app.google_login_enabled')) { ... }

// ✓
if (SocialProviders::configured('google')) { ... }
```

---

## Table

`social_accounts` — `user_id`, `provider`, `provider_id`, `avatar`, encrypted
`token` and `refresh_token`, `expires_at`.
`UNIQUE (provider, provider_id)`.

Linked to the user and not to an account: the link is to the person, and the
same person may belong to several accounts.
