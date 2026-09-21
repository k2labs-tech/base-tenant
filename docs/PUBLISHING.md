# Publishing to Packagist

`k2labs/base-tenant` is published on Packagist from the public GitHub repository
`k2labs-tech/base-tenant`. Packagist reads the repository; it never receives an
upload. A release is a Git tag, and everything below is about making the tag
right.

## How Packagist sees the repository

| Git | Packagist version | Who installs it |
|---|---|---|
| Tag `v3.0.0` | `3.0.0` | `composer require k2labs/base-tenant` (`^3.0`) |
| Branch `main` | `dev-main`, aliased `3.x-dev` | `k2labs/base-tenant:3.x-dev` |
| Branch `dev` | `dev-dev`, aliased `3.x-dev` | `k2labs/base-tenant:dev-dev` |
| Any other branch | `dev-<branch>` | Only if asked for by name |

- **The version comes from the tag, never from `composer.json`.** There is no
  `version` key, and there must not be one: it would override the tags.
- **Every branch is imported,** so every branch needs a valid `composer.json`.
  A branch with an invalid one is skipped and logged on the package page —
  that is what happened to `main` and `dev` while `homepage` was an array.
- **The branch alias** (`extra.branch-alias`) maps `dev-main` and `dev-dev` to
  `3.x-dev`. Move it to `4.x-dev` when `main` starts carrying 4.0 work.

## One-time setup

Done already; kept here so it can be redone.

1. Sign in to [packagist.org](https://packagist.org) with the account that will
   own the package, and **Submit** `https://github.com/k2labs-tech/base-tenant`.
   The package name comes from `composer.json` (`k2labs/base-tenant`), and the
   `k2labs` vendor is claimed by the first package submitted under it.
2. **Keep it updating automatically.** Link the Packagist account to GitHub
   (*Profile → Settings → Connected accounts*); Packagist then installs its hook
   on the repository and re-reads it on every push. Without that, go to the
   package page and use **Update** after each release, or add the webhook by
   hand in GitHub (*Settings → Webhooks*, payload URL
   `https://packagist.org/api/github?username=<packagist-user>`, content type
   `application/json`, secret: the API token from the Packagist profile).
3. Add the other maintainers on the package page (*Maintainers*), so a release
   does not depend on one person.

## Releasing a version

Versions follow [Semantic Versioning](https://semver.org): a breaking change —
a new required Laravel version, a renamed config key, a removed command — is a
major version.

1. **Everything is on `dev` and green.**

   ```bash
   composer validate --strict
   vendor/bin/pest
   ```

   `composer validate --strict` is the same check Packagist runs; if it fails,
   Packagist skips the branch.

2. **Close the changelog.** In `docs/CHANGELOG.md`, rename `[Unreleased]` to
   `[X.Y.Z] - YYYY-MM-DD` and open a new, empty `[Unreleased]` above it. A
   breaking release also gets a section in `docs/UPGRADE.md`.

3. **Integrate into `main`.** `main` receives one squashed commit per
   integration; `dev` keeps the commit-by-commit history.

   ```bash
   git switch dev
   git commit-tree "dev^{tree}" -p main -m "release: X.Y.Z" | xargs git update-ref refs/heads/main
   git diff --quiet main dev && echo "main == dev"
   ```

4. **Tag `main` and push.** The tag is annotated and prefixed with `v`.

   ```bash
   git tag -a vX.Y.Z main -m "X.Y.Z"
   git push origin dev main vX.Y.Z
   ```

5. **Check Packagist.** The version shows up within a minute when the GitHub
   hook is in place. The package page's update log names any branch or tag it
   skipped, and why.

6. **Check the archive a user gets.** `.gitattributes` keeps tests, internal
   specs and planning documents out of it. To see exactly what ships:

   ```bash
   git archive --format=tar vX.Y.Z | tar -t | cut -d/ -f1-2 | sort -u
   ```

   It must contain `src/`, `config/`, `database/`, `resources/`, `routes/`,
   `stubs/`, `docs/agents/` (read at runtime by `k2labs-base:publish-agent-docs`)
   and `tests/TenancyAssertions.php` (autoloaded for host applications), and
   must not contain `tests/Feature/` or `phpunit.xml`.

### Fixing a release

A published tag is never moved or deleted: someone may already have installed
it, and Composer caches archives by version. Fix forward with a new patch
release. If a version is broken enough that nobody should install it, say so in
the changelog and release the fix as soon as possible.

## Before each release: what not to ship

- **No `repositories` block pointing at a local path.** In this package it is
  only untidy: Composer ignores the `repositories` of a dependency. In the
  starter kit, which is installed as the root package, it is fatal — Composer
  aborts with *the `url` supplied for the path repository does not exist* on
  every machine without that directory. The Flux Pro repository is kept on
  purpose, for the package's own development.
- **No secrets.** The repository is public: `.env`, `auth.json` and Flux Pro
  credentials stay out of it (`.gitignore` covers them).
- **`minimum-stability: dev`** in `composer.json` only affects the package's own
  development install; it does not reach applications that require it.

## Every branch must stay valid

Packagist imports all branches on the remote, not only `main`. A branch left
behind with an older `composer.json` still appears as a `dev-<branch>` version.
Either keep its `composer.json` metadata in line with `main` — name, licence,
autoload, homepage — or delete the branch once its work is merged.

## Licence

The package is source-available, not open source: `license` in `composer.json`
is `proprietary` and the terms are in [`LICENSE.md`](../LICENSE.md). Packagist
shows the package as proprietary, and anyone can install it; what they may do
with it is governed by the licence, not by Packagist.

## The starter kit

`k2labs-tech/starter-kit` is published on Packagist the same way and requires
`k2labs/base-tenant: ^3.0`. It is released alongside the package whenever a
release changes what a new project gets.

Its `composer.json` must never carry a `path` repository for the package. The
kit is installed as the *root* package, so its `repositories` block is honoured
in full, and one pointing at a developer's directory breaks `composer install`
for everyone else. Working against a local checkout of the package is set up in
the generated project instead:

```bash
composer config repositories.k2labs/base-tenant \
  '{"type":"path","url":"~/Projects/base-tenant","options":{"symlink":true,"versions":{"k2labs/base-tenant":"3.0.1"}}}'
```
