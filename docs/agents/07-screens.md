# Screens and routes

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-18

## When to use this

Finding which screen sits at which URL, what it needs to let somebody in, and
which module has to be on for the route to exist at all. Also: the full list of
registered component aliases.

## When NOT to use this

Embedding a component in your own view — that is [06-ui.md](06-ui.md). Building
a screen of your own — that is the table pattern in
[02-conventions.md](02-conventions.md).

---

## Full-page screens

All under `config('base-tenant.routes.prefix')` and `...routes.middleware`
(`['web']`), and skipped entirely when `BASE_TENANT_ROUTES_ENABLED=false`.

### Authenticated

Behind `config('base-tenant.routes.auth_middleware')`, which defaults to
`['auth', 'verified', 'base-tenant.subscription']`.

Plain views with no component and no permission: `base-tenant.dashboard`
(`/dashboard`), `base-tenant.profile` (`/profile`), `base-tenant.upgrade`
(`/upgrade`), and `impersonate.leave`. The rest:

| Route name | URL | Component | Requires | Module |
|---|---|---|---|---|
| `base-tenant.users.index` | `/users` | `base-tenant.user-manager` | `users.view` | |
| `base-tenant.users.create` | `/users/create` | `base-tenant.edit-user` | `users.create` | |
| `base-tenant.users.edit` | `/users/{user}/edit` | `base-tenant.edit-user` | `users.update` | |
| `base-tenant.accounts.index` | `/accounts` | `base-tenant.account-manager` | `accounts.view` | |
| `base-tenant.accounts.create` | `/accounts/create` | `base-tenant.edit-account` | `accounts.create` | |
| `base-tenant.accounts.edit` | `/accounts/{account}/edit` | `base-tenant.edit-account` | `accounts.update` | |
| `base-tenant.roles.index` | `/roles` | `base-tenant.role-manager` | `roles.view` | |
| `base-tenant.menus.index` | `/navigation` | `base-tenant.navigation-manager` | `menus.update` | |
| `base-tenant.features.index` | `/features` | `base-tenant.feature-manager` | `features.view` (editing: super admin) | |
| `base-tenant.settings.index` | `/settings` | `base-tenant.account-settings` | `settings.view` (saving: `settings.update`) | |
| `base-tenant.invitations.index` | `/invitations` | `base-tenant.invitation-manager` | `invitations.view` | |
| `base-tenant.activity` | `/activity` | `base-tenant.activity-log` | `activity.view` | |
| `base-tenant.notifications.index` | `/notifications` | `base-tenant.notifications.index` | — (own notifications) | |
| `base-tenant.usage` | `/usage` | `base-tenant.usage-manager` | `usage.view` | metering |
| `base-tenant.files.index` | `/files` | `base-tenant.file-library` | `files.view` (delete: `files.delete`) | files |
| `base-tenant.languages.index` | `/languages` | `base-tenant.language-manager` | `languages.manage` | languages |
| `base-tenant.transfers.index` | `/transfers` | `base-tenant.transfer-manager` | `transfers.view` | transfer |
| `base-tenant.connections.index` | `/connections` | `base-tenant.connection-manager` | `connections.manage` | connections |
| `base-tenant.terms` | `/terms` | `base-tenant.accept-terms` | — | gdpr |

The last six exist only when their module is on. **`route()` on a name from a
disabled module throws** -- link to one from inside the same gate, or read the
link from the menu, which is already filtered.

Permissions are enforced in `mount()`, not on the route -- either
`$this->authorize()` through a policy or `abort_unless(...->hasPermission())`.
Adding route middleware as well is redundant, not safer.

### Guest and auth

| Route name | URL | Component | Middleware |
|---|---|---|---|
| `base-tenant.home` | `/` | redirect to dashboard or login; the presale landing while pre-sale is open | — |
| `base-tenant.login` | `/login` | `Auth\Login` | `guest` |
| `base-tenant.register` | `/register` | `Auth\Register` | `guest` |
| `base-tenant.password.request` | `/forgot-password` | `Auth\ForgotPassword` | `guest` |
| `base-tenant.password.reset` | `/reset-password/{token}` | `Auth\ResetPassword` | `guest` |
| `base-tenant.two-factor.challenge` | `/two-factor-challenge` | `base-tenant.two-factor-challenge` | `guest` |
| `base-tenant.verification.notice` | `/verify-email` | `Auth\VerifyEmail` | `auth` |
| `base-tenant.verification.verify` | `/verify-email/{id}/{hash}` | controller | `auth`, `signed`, `throttle:6,1` |
| `base-tenant.password.confirm` | `/confirm-password` | `Auth\ConfirmPassword` | `auth` |
| `base-tenant.password.change` | `/password/change` | `Auth\ForcePasswordChange` | `auth` |
| `base-tenant.logout` | `POST /logout` | closure | `auth` |
| `base-tenant.invitations.accept` | `/invitations/accept/{token}` | controller | public |
| `base-tenant.social.redirect` / `.callback` | `/auth/{provider}/…` | controller | social module |

### Subscriptions

Registered only when `BASE_TENANT_SUBSCRIPTION_ENABLED` is on:
`base-tenant.checkout` (`/checkout`, `base-tenant.no-subscription`), and behind
`base-tenant.subscription`: `base-tenant.checkout.success`,
`base-tenant.checkout.cancel`, `base-tenant.billing`.

---

## The rest of the registry

Registered but not routed. Listed so an agent knows the alias exists before
writing a second one.

| Alias | Class |
|---|---|
| `base-tenant.auth.login` | `Login` |
| `base-tenant.auth.register` | `Register` |
| `base-tenant.auth.forgot-password` | `ForgotPassword` |
| `base-tenant.auth.reset-password` | `ResetPassword` |
| `base-tenant.auth.confirm-password` | `ConfirmPassword` |
| `base-tenant.auth.verify-email` | `VerifyEmail` |
| `base-tenant.auth.force-password-change` | `ForcePasswordChange` |
| `base-tenant.two-factor-authentication` | `TwoFactorAuthentication` |
| `base-tenant.forms.login-form` | `LoginForm` |
| `base-tenant.profile.update-profile-information-form` | `UpdateProfileInformationForm` |
| `base-tenant.profile.update-password-form` | `UpdatePasswordForm` |
| `base-tenant.profile.delete-user-form` | `DeleteUserForm` |
| `base-tenant.preferences` | `Preferences` |

