# Security policies — the rules a customer sets for their own account

**Module:** security · **Package version:** v3 · **Last reviewed:** 2026-09-01
**Switch:** `BASE_TENANT_SECURITY_ENABLED` (on by default)

## When to use this

Whenever something must respect what the customer's own IT administrator
decided: a new way of signing in, a new way of joining an account, a new
surface that should honour the IP allowlist.

## When NOT to use this

- Permissions. Those are per-role and per-account — see [02](02-conventions.md).
- Platform-wide rules. This is the *customer's* policy; installation-wide
  settings belong in `config/base-tenant.php`.

---

## API

```php
use Base\Tenant\Facades\Security;

Security::for($account);                       // SecurityPolicySettings
Security::requiresTwoFactor($account);
Security::twoFactorIsOverdue($user, $account); // past the grace period, still no 2FA
Security::allowsEmail('ada@customer.com', $account);
Security::allowsIp('198.51.100.7', $account);
Security::enforcesIp($account);                // blocking, not just warning
Security::sessionTimeoutMinutes($account);
Security::update($account, ['requireTwoFactor' => true]);   // saves and audits
Security::startTwoFactorClockFor($user, $account); // when somebody joins an account with the rule on
```

Values live in the typed settings store under the `security` group, described
by `SecurityPolicySettings`. Adding a rule is a public typed property on that
class and nothing else.

---

## The rules that matter

**Every default is the permissive one.** A rule that arrived switched on with
an upgrade would lock people out of an account that asked for no change.
`SecurityPolicyTest` asserts the defaults, so relaxing this is a red suite.

**An empty allowlist never blocks.** Switching `ipMode` to `enforce` with an
empty list would refuse every member of the account, including whoever is
editing the rule, with no way back from inside the product. `allowsIp()`
returns true for an empty list and `enforcesIp()` returns false.

**The screen refuses to lock you out.** Saving `enforce` from an address the
list does not cover is rejected with the address named. `addCurrentIp()` exists
so the usual path does not require an administrator to know their public IP.

**The two-factor clock starts once.** `two_factor_required_from` is stamped
when the rule is switched on, and only for users who have no stamp yet.
Counting from `created_at` would hand a year-old user a deadline in the past;
counting from "now" every request means the deadline never arrives; and
re-stamping on every toggle would let anyone reset their own grace period by
asking an administrator to switch the rule off and on. Somebody joining an
account that *already* has the rule on gets their stamp the moment they join
(`Security::startTwoFactorClockFor()`, called from `InvitationService::accept()`
and the user editor), so a two-year-old user invited today is not overdue on
their first request.

**Email domains are enforced in the service, not the screen.** An invitation
can come from a command or a job, and the point of the rule is that nobody
gets into the account around it. `InvitationService::send()`, `resend()` and
`accept()` all throw `DomainNotAllowedException`, so a pending invitation to
an address the rule no longer allows can neither be re-mailed nor accepted.
`accept()` also refuses a signed-in user whose address is not the one the
invitation was mailed to (`InvitationException`) — otherwise anybody holding
the link would join under an address the administrator never approved.

---

## Middleware

| Alias | What it does |
|---|---|
| `base-tenant.two-factor` | Sends anyone past their grace period to the profile screen to set up a second factor |
| `base-tenant.ip-allowlist` | Blocks in `enforce`, records in `warn`, does nothing in `off` |
| `base-tenant.session-timeout` | Ends a session idle longer than the account allows |

None of them is applied by default — add them to
`base-tenant.routes.auth_middleware`, or to your own route groups. Each leaves
an escape hatch reachable (the profile screen, logout) so a user is never in a
loop with no way to comply and no way to leave.

**They run on Livewire updates too, and that is not free.** Every action in
the package's screens is a POST to Livewire's update endpoint, which only
re-applies the middleware in its *persistent* list. The provider registers all
four (`BaseTenantServiceProvider::persistentMiddleware()`, including
`base-tenant.track-session`), so on an update Livewire rebuilds the original
page request — same session, the page's own route — and runs them against it.
The update request itself is skipped (`DefersToPersistentMiddleware`) — but
only when the class reached it through a route group, because that is the
only case the replay covers: Livewire gathers the page route's middleware and
its groups, never the kernel's global stack. Attached globally, the middleware
still run on the update request as before, and their `routeIs()` escape
hatches will not match there. Put them on your page routes or in `web`, not
in the kernel, and never only on the Livewire endpoint.

Inside the replay Livewire keeps a redirect and discards any other response,
so the JSON refusals are thrown with `abort()`, never returned — a returned
403 would let the action run for anyone who adds `Accept: application/json`.

`Security::for()` memoises the policy per account for the request, because
the three middleware and the screen ask for it several times per Livewire
update. `update()` refreshes the cache, and the provider clears it when a
queue job or an Octane request starts, so a long-lived worker never serves a
policy another worker has since tightened. A host writing the `security`
settings group directly must call `Security::forget()`.

Two things a `wire:poll` tick must not be. It is not activity:
`EnforceSessionTimeout` recognises a poll (no property updates, `$refresh`
the only call) and does not re-stamp `last_activity`, or a tab left open
would keep its owner signed in for ever. And it is not a new warning:
`EnforceIpAllowlist` in `warn` mode logs one `ip_would_be_blocked` row per
session and address, not one per request.

`warn` mode exists because going straight to `enforce` is how an account locks
itself out on a Friday evening. It writes `security.ip_would_be_blocked` to the
activity log so an administrator can read a day of real traffic and find the
office VPN they forgot.

---

## Active sessions

Same module, same switch, second config key:
`base-tenant.security.sessions`.

```php
use Base\Tenant\Facades\Sessions;

Sessions::forUser($user);                  // open sessions, most recent first
Sessions::revoke($session);
Sessions::revokeOthers($user);             // keeps the one making the request
Sessions::revokeAll($user);                // including the current one
Sessions::isRevoked($request);
Sessions::touch($request, $user);          // the tracking middleware calls this
Sessions::prune(30);
```

**`UserSession` and the `user_sessions` table, not Laravel's `sessions`.** Two
reasons, both load-bearing. Laravel's table only exists under the database
session driver, and this has to work whatever the host chose. And revoking by
deleting a row only works for that driver — here revocation is a flag the
middleware reads, which ends the session on any driver.

**The cost, stated plainly:** a revoked session ends on its *next request*, not
the instant the button is pressed. The confirmation dialog says so.

**The session id is hashed** (`UserSession::fingerprint()`), never stored or
displayed in the clear. It is a bearer credential: whoever holds it *is* the
session, so a leaked backup of this table would otherwise be a set of live
logins. `SessionExporter` omits it from GDPR exports for the same reason,
while including the address and device, which *are* personal data and must
appear in a disclosure.

**`revokeOthers()` keeps the current session** — signing somebody out of the
screen they are using to secure their account is how they stop halfway
through. An administrator acting on somebody else has no "current" to keep, so
the screen calls `revokeAll()` instead.

**A revoked row is never refreshed.** `touch()` returns early, so a closed
session cannot keep writing a fresh `last_active_at` and look active in raw
data. It also returns the row, so the tracking middleware reads it once for
both the revocation check and the touch, and it skips the write when the same
browser touched the same row from the same address within
`SessionManager::TOUCH_INTERVAL_SECONDS` — a `wire:poll` tick is not new
information.

`DeviceParser` turns a user agent into a readable name. Deliberately
approximate: the screen's job is to let somebody recognise "that is not my
phone", not to fingerprint devices. Order matters in it — Edge and Opera both
claim to be Chrome, and Chrome claims to be Safari.

| Alias | What it does |
|---|---|
| `base-tenant.track-session` | Records the session and signs out one that was revoked |

| Command | |
|---|---|
| `k2labs-base:prune-sessions` | Drops rows past the retention window. Scheduled daily |

The screen is `base-tenant.profile.active-sessions`, embedded in the profile.
Passing `:user` shows somebody else's and requires `users.update`; the
permission is re-checked on every action, not only on mount, because a
Livewire component's public state travels with the request.

---

## Screens and permissions

`base-tenant.security-policy-manager` at `/security`, gated by `security.view`
and `security.update`. It shows how many members have no second factor *before*
the rule is saved, which is the difference between a decision and a surprise.

---

## Extension point

`SecurityPolicySettings` extends `SettingsSchema`, so a host application can
read and write it like any other typed settings group. `IpRange` is the CIDR
matcher — `matchesAny()`, `matches()`, `isValidEntry()` — usable anywhere an
address has to be checked against a list.

---

## Do not

| Do not | Do instead |
|---|---|
| Read `security` settings directly | `Security::for($account)` |
| Add a second place that checks email domains | `Security::allowsEmail()` |
| Enforce an IP rule without a `warn` step | Ship `warn` first, read the log, then enforce |
| Default a new rule to the strict value | Default permissive; let the customer opt in |
| Skip the audit on a policy change | `Security::update()` writes it |
| Read or store a raw session id | `UserSession::fingerprint()`; it is a credential |
| Revoke by deleting the session row | `Sessions::revoke()`, so it works on any driver |
