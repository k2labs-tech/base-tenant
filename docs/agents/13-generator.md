# Generator — new vertical modules

**Module:** M4 · **Package version:** v2 · **Last reviewed:** 2026-08-12

## When to use this

Starting any new CRUD area of the application: bookings, properties, invoices,
cases. Anything that is a tenant-owned table with screens over it.

## When NOT to use this

- A one-off screen with no table behind it.
- Extending something that already exists — edit it.
- A model that is not tenant-owned. The generator assumes `account_id`.

---

## Use it

```bash
php artisan k2labs-base:make-module Booking \
    --fields="reference:string,guests:integer,starts_at:date,notes:text:nullable" \
    --files=documents \
    --pretend
```

Drop `--pretend` to write. `--force` overwrites files that already exist.

### Field types

`string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `uuid`,
`json`. Add `:nullable` as a third segment.

Each type carries its migration column, its cast, its validation rule, its
input type and a factory value, so the four never disagree about what a field
is.

---

## What comes out

| File | Notes |
|---|---|
| `app/Models/{Class}.php` | `BelongsToAccount` + `LogsActivity` + UUIDs + casts |
| `database/migrations/*` | `account_id`, the fields, soft deletes |
| `database/factories/*` | A working definition for every field |
| `app/Policies/{Class}Policy.php` | Permission checks, not ownership checks |
| `app/Livewire/{Class}/Index.php` | The package's table pattern, sorting whitelisted |
| `app/Livewire/{Class}/Form.php` | One component for create and edit |
| `resources/views/livewire/{kebab}/*` | Toolbar, sticky header, skeleton, designed empties, delete modal |
| `lang/{en,es}/{kebab}.php` | Both locales, always |
| `tests/Feature/{Class}Test.php` | Isolation, listing, permission, create, delete |
| `docs/agents/app/{kebab}.md` | An agent doc in the same format as these |

And it appends: the routes, and the permission group in
`config/base-tenant.php`.

The generated model is tenant-scoped and logged, the policy asks about
permissions, and the first test that ships is the tenant isolation one — the
guarantee a hand-written module is most likely to lose and least likely to be
missed.

---

## Re-running it

Everything appended to an existing file is wrapped in
`// base-tenant:module:{kebab}:begin` … `:end`. A second run replaces its own
block and leaves everything around it alone, so adding a field later is one
more `--force` rather than a manual merge.

Permissions land at the `// base-tenant:permissions` marker if the config has
one, and at the end of the file if not.

Files that already exist are kept unless `--force` is passed.

---

## Making it yours

```bash
php artisan vendor:publish --tag=base-tenant-stubs
```

The generator reads `stubs/base-tenant/module/*.stub` from the project when
they are there, so the house style survives a package update.

---

## After generating

```bash
php artisan migrate
php artisan k2labs-base:sync-roles && php artisan k2labs-base:sync-menus
```

Then fill in the "when NOT to use this" section of the generated agent doc. A
document that only says when to use something is half a document.

---

## Anti-patterns

```php
// ✗ A hand-written CRUD that loses the scope, the log, the sort whitelist and
//   the isolation test, and looks fine in review.
class BookingController extends Controller { ... }

// ✓
php artisan k2labs-base:make-module Booking --fields="..."
```
