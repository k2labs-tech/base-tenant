# Conventions

**Module:** core · **Package version:** v2 · **Last reviewed:** 2026-08-12

## When to use this

Whenever you add a screen, a permission, a menu entry, a setting, a command or
a string. These are the conventions the rest of the codebase already follows.

## When NOT to use this

Nothing here applies to code with no interface and no tenancy — a pure
calculation, a value object, a parser.

---

## Permissions

Declared in `config/base-tenant.php` under `permissions`, grouped by area, and
written to the database by `php artisan k2labs-base:sync-roles`.

```php
'permissions' => [
    'bookings' => ['bookings.view', 'bookings.create', 'bookings.update', 'bookings.delete'],
],
```

Grant them to roles in the same file. `'bookings.*'` expands to every
permission in the group.

Check them:

```php
Auth::user()->hasPermission('bookings.update');    // in the account in context
abort_unless(Auth::user()->hasPermission('bookings.view'), 403);
@can('update', $booking)                            // through a policy
```

### Anti-patterns

```php
// ✗ Roles are per account and configurable; names are not a permission model.
if (Auth::user()->role === 'admin') { ... }

// ✓
if (Auth::user()->hasPermission('bookings.update')) { ... }
```

---

## Plan features

Features are what a plan includes. Permissions are what a person may do. Both
have to say yes.

```php
use Base\Tenant\Facades\Feature;

Feature::active('api_access');                  // boolean feature
Feature::limit('max_users');                    // -1 means unlimited
Feature::withinLimit('max_users', $count);      // pass usage for anything not metered
Feature::withinLimit('max_storage_gb');         // metered: usage is read for you
Feature::for($account)->active('export');
```

In Blade: `@feature('export') ... @endfeature`.
On a route: `->middleware('base-tenant.feature:export')`.

Overrides for one account (trials, pilots, grandfathered customers) go through
`Feature::for($account)->set('max_users', 25, $expiresAt)` — never by inventing
a plan.

---

## Menus

Declared in code, stored in the database so each account can reorder, rename
and hide entries.

```php
use Base\Tenant\Facades\Menu;
use Base\Tenant\Menu\MenuBuilder;

// In a service provider's boot()
Menu::register('main', function (MenuBuilder $menu): void {
    $menu->item('bookings')
        ->label('app::navigation.bookings')   // a translation key, not a string
        ->icon('calendar')
        ->route('app.bookings.index')
        ->permission('bookings.view')
        ->position(50)
        ->badge(fn (): int => Booking::pending()->count());
});
```

Then `php artisan k2labs-base:sync-menus`.

**Trap:** the label is stored in the database as the key. Never store a
translated string — the account would be stuck in whatever language the
command ran in.

---

## Settings

Typed, per account or per user.

```php
class BookingSettings extends Settings
{
    public bool $require_deposit = false;
    public int $cancellation_hours = 24;

    public static function group(): string { return 'bookings'; }
}
```

Register with `Settings::register(BookingSettings::class)`, then:

```php
$settings = Settings::for($account)->get(BookingSettings::class);
$settings->cancellation_hours;
```

Do not add columns to `accounts` for configuration. Settings get an editor
screen, validation and history for free.

---

## Activity

```php
use Base\Tenant\Traits\LogsActivity;

class Booking extends Model
{
    use LogsActivity;
}
```

Records creates, updates and deletes with the causer, the account and the
changed attributes.

These attributes are stripped before anything is written: `password`,
`remember_token`, `two_factor_secret`, `two_factor_recovery_codes`,
`credentials`, `secret`, `token`, `refresh_token`. The last four matter because
the modules that hold third-party credentials encrypt those columns at rest, and
a change to one of them landing in plain text in the audit trail would undo that
on the first edit.

---

## Translations

**Every user-facing string goes through `__()`.** Screens, buttons, validation
messages, mail, toasts, empty states.

- Package strings: `base-tenant::file.key`, in `resources/lang/{en,es}/`.
- Application strings: your own namespace, same two locales.
- Both locales, always. `TranslationKeysTest` fails on a key used in code that
  is missing from either.
- A key built at runtime (`__('x.names.'.$key)`) cannot be checked by that
  test, so give it a fallback in code.

---

## Commands

Every package command lives under `k2labs-base:`. The v1 `base-tenant:*` names
still resolve as deprecated aliases and are removed in v3.

```
k2labs-base:install                k2labs-base:sync-roles
k2labs-base:sync-menus             k2labs-base:prune-activity-log
k2labs-base:scaffold               k2labs-base:eject
k2labs-base:make-module            k2labs-base:publish-agent-docs
k2labs-base:report-usage           k2labs-base:reconcile-storage
k2labs-base:lang-status            k2labs-base:lang-push
k2labs-base:lang-pull              k2labs-base:check-connections
k2labs-base:import-suppressions    k2labs-base:export-user-data
k2labs-base:purge-deleted          k2labs-base:presale-open
```

A command that should run on a schedule registers itself in the package's
`registerSchedule()`, gated by its module.

---

## Management screens

Every table screen in this codebase follows one pattern. Copy
`UserManager` — it is the reference implementation.

```php
class BookingManager extends Component
{
    use InteractsWithTable;
    use WithPagination;

    protected function sortableColumns(): array
    {
        return ['reference', 'created_at'];   // a whitelist, always
    }
}
```

`InteractsWithTable` gives search, sort, page size and density, all as `#[Url]`
so the state travels in the link. What it will not do is sort by a column you
did not declare: `sortBy` arrives from the query string, and an unfiltered
column name there is an injection point.

The page has, top to bottom: breadcrumbs, a header with title + count +
primary action, an optional context strip, then a panel holding toolbar,
table and footer. The toolbar is always **search | filter | view**, in that
order, with the three zones separated.

Empty, loading and error states are designed, not a centred sentence. The
skeleton row must have exactly as many cells as a real row, or the table
visibly bends as it loads.

`TablePatternTest` discovers every component using the trait and requires an
entry describing how to seed it. A new screen that skips this fails the suite
rather than silently escaping the contract.

---

## Testing

- Pest, `php artisan test` or `./vendor/bin/pest`.
- **Before running tests, confirm `phpunit.xml` has `DB_CONNECTION=sqlite` and
  `DB_DATABASE=:memory:` uncommented.** Otherwise the suite runs against the
  development database and wipes it.
- Minimum per module: one `assertTenantIsolated`-style test and one feature
  test of the main flow.
- A test for a guard must fail when the guard is removed. Check it by actually
  removing the guard, running the test, and putting it back.
