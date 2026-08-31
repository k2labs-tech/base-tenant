# Domains — subdomains and custom domains

**Module:** domains · **Package version:** v3 · **Last reviewed:** 2026-09-01
**Switch:** `BASE_TENANT_DOMAINS_ENABLED` (on by default)

## When to use this

Whenever the question is "what host serves this account?": building a link to a
customer's space, letting them choose their address, or letting them point a
domain of their own at the product.

## When NOT to use this

- Resolving the current tenant. That already happens —
  `DomainTenantResolver` runs before your code does. Read `Tenant::current()`.
- Database-per-tenant. Not what this is. See [01](01-architecture.md).

---

## API

```php
use Base\Tenant\Facades\Domain;

// Subdomain
Domain::isSubdomainAvailable('acme');            // well formed, free, not reserved
Domain::claimSubdomain($account, 'acme');        // throws DomainException on refusal
Domain::releaseSubdomain($account);

// Custom domains
$domain = Domain::addDomain($account, 'app.customer.com');   // starts pending
Domain::verify($domain);                          // reads DNS, returns bool
Domain::makePrimary($domain);                     // verified domains only
Domain::removeDomain($domain);
Domain::domainsFor($account);

// Where this account lives
Domain::hostFor($account);                        // "app.customer.com"
Domain::urlFor($account);                         // "https://app.customer.com"
```

`hostFor()` answers in one place: the primary verified domain if there is one,
otherwise the subdomain under the first central domain, otherwise the central
domain itself. Build links with it rather than with `config('app.url')`, which
does not know which customer is being addressed.

---

## The rule that matters

**A custom domain is only served once its owner has proved they control it.**

The proof is a TXT record at `_base-tenant-verify.<hostname>` holding the
row's `verification_token`. `DomainTenantResolver` filters on
`AccountDomain::scopeVerified()`, and that filter is the guard of the whole
capability: without it, anybody who can point DNS at this installation would be
served as the account that typed the name into the form.

`DomainsTest` fails if the scope stops being applied. Do not remove it to "make
local development easier" — add the hostname to `central_domains` instead.

---

## Models

`AccountDomain` — `account_domains`. Columns: `hostname` (unique across the
installation), `verification_token`, `status` (`pending` / `verified` /
`failed`), `is_primary`, `verified_at`, `last_checked_at`, `last_error`.

**It deliberately does not use `BelongsToAccount`.** The resolver reads it
while the tenant is still being decided, so a global scope would return nothing
and no custom domain would ever resolve. Every read from the interface scopes
by hand — see `Livewire\DomainManager::findDomain()`. If you query this model
yourself, scope it yourself.

---

## Extension point

`DnsLookup` — one method, `txt(string $host): array`. Bound to
`SystemDnsLookup` by default. Rebind it to fake DNS in a test, or to a specific
resolver in an installation behind split horizon.

```php
app()->bind(DnsLookup::class, fn () => new MyLookup);
```

`DomainVerifier` compares the published values against the expected token,
tolerating the quotes and padding providers add.

---

## Screens and commands

| | |
|---|---|
| `base-tenant.domain-manager` | `/domains`, tenant screen. Permissions `domains.view` / `domains.update` |
| `k2labs-base:verify-domains` | Re-checks domains due for verification. Scheduled daily. `--domain=` for one, `--all` for every one |

A verified domain that stops resolving is **not** demoted on a single bad
answer: DNS fails transiently, and taking a customer's production hostname out
of service for a blip would be worse than the problem. It is re-checked and
`last_error` records why.

---

## Configuration

`base-tenant.domains` — `subdomains.reserved` is the list of names a customer
may not take, and it exists because `www`, `api` and `mail` collide with what
the installation itself publishes. `custom.max_per_account` caps how many
domains an account registers (`0` for no ceiling). `custom.target` is the CNAME
shown in the setup instructions.

`tenancy.central_domains` is what a subdomain hangs from, and a hostname inside
it can never be claimed as a custom domain.

---

## Do not

| Do not | Do instead |
|---|---|
| Build a customer URL from `config('app.url')` | `Domain::urlFor($account)` |
| Query `AccountDomain` without scoping by account | Scope it, or go through `Domain::domainsFor()` |
| Drop `verified()` from the resolver | Add the host to `central_domains` |
| Add a reserved name check of your own | Extend `domains.subdomains.reserved` |
| Trust `accounts.domain` for new work | It is the legacy column; new domains live in `account_domains` |
