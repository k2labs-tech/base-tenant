# Onboarding checklist

**Module:** Q4 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_ONBOARDING_ENABLED` (on by default)

## When to use this

Getting a new account from "signed up" to "actually set up": profile filled in,
someone invited, the first real record created.

## When NOT to use this

- A product tour or tooltips. This is a list of work, not a walkthrough.
- Anything that has to be done before the account can be used at all — that is
  a required field on registration, not a checklist item.

---

## Declare steps

```php
// config/base-tenant.php
'onboarding' => [
    'steps' => [
        'first_property' => [
            'label' => 'app::onboarding.first_property',
            'description' => 'app::onboarding.first_property_description',
            'route' => 'properties.create',
            'completed' => \App\Onboarding\HasAProperty::class,
        ],
    ],
],
```

The check is any invokable class taking an `Account` and returning a boolean:

```php
class HasAProperty
{
    public function __invoke(Account $account): bool
    {
        return Tenant::runFor($account, fn () => Property::query()->exists());
    }
}
```

A step with no `completed` class is never marked done. Claiming otherwise would
hide work that has not happened.

---

## Show it

```blade
<livewire:base-tenant.onboarding.checklist />
```

Already on the package dashboard. It renders **nothing** once the account has
finished or dismissed it, rather than an empty card: a list with every box
ticked is a reminder of work already done, in the best space on the page.

---

## API

```php
use Base\Tenant\Facades\Onboarding;

Onboarding::steps();        // each with its complete flag
Onboarding::progress();     // 0-100
Onboarding::shouldShow();
Onboarding::dismiss();
Onboarding::refresh($account);
```

Checks are memoised per account per request. Each one usually costs a query,
the widget asks for all of them, and several appear twice on a dashboard —
without the memo the checklist is the most expensive thing on the page. Call
`refresh()` after doing something that would change an answer within the same
request.

`onboarded_at` on the account is both "finished" and "dismissed". The two do
not need telling apart afterwards, and one column beats two.

---

## Anti-patterns

```php
// ✗ A boolean column per step, migrated every time a step is added or
//   removed, and wrong for every account that existed before it.
$account->has_invited_team;

// ✓ A check that asks the question when it is asked.
'completed' => \App\Onboarding\TeamInvited::class,
```
