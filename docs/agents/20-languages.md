# Languages

**Module:** Q1 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_LANGUAGES_ENABLED` (on by default)

## When to use this

Offering a language, withdrawing one, or asking which languages this
installation currently serves.

## When NOT to use this

Deciding the locale of a single request — that is `SetLocale`'s job and it
already runs. Do not call `app()->setLocale()` in a controller.

---

## API

```php
use Base\Tenant\Facades\Language;

Language::enabled();          // Collection<Language>, in display order
Language::codes();            // ['en', 'es']
Language::default();          // 'en'
Language::isEnabled('fr');
Language::resolve($preferred);// the locale to actually use

Language::enable('fr');       // live, no deploy
Language::disable('fr');
Language::setDefault('es');   // enables it too, and clears the previous default
Language::reorder('es', 1);
```

The catalogue is a table, not a config array. That is the entire point: adding
Catalan should not need a release. `base-tenant.languages.seed` only seeds a
fresh install; after that the table is the source of truth.

The list is cached and the cache is dropped by the four methods that can
change it, and by nothing else.

---

## Rules that are enforced

**The default language cannot be disabled.** It would leave the fallback
pointing at a language nobody can see and every untranslated string with
nowhere to go. `disable()` throws; the admin screen shows the message.

**A preference pointing at a disabled language falls back.** Someone whose
language was switched off yesterday still has it stored. Honouring it would
show them a half-translated interface with no way back, so `SetLocale`
resolves every preference against what is actually enabled.

---

## Coverage

```
php artisan k2labs-base:lang-status
php artisan k2labs-base:lang-status ca --missing
php artisan k2labs-base:lang-status --fail-under=90
```

Enabling an incomplete language is legitimate — the fallback covers the holes.
What the report changes is that the holes are visible. The admin screen shows
the same figure per language.

`--fail-under` is for CI, and should be applied only to the locales the
package itself ships (`en`, `es`).

---

## LangSyncer (optional)

Off unless `LANGSYNCER_API_KEY` and `LANGSYNCER_PROJECT` are set. Project
level, not tenant level: the translations belong to the installation.

```bash
php artisan k2labs-base:lang-push            # source keys up
php artisan k2labs-base:lang-pull ca --enable # finished translations down
```

`POST /webhooks/langsyncer`, signed with `LANGSYNCER_WEBHOOK_SECRET`, queues a
pull when a translation is completed upstream. That is the piece that makes a
new language appear without a release.

Writing is a merge: a locale that comes back with two thirds of its keys does
not delete the third the project wrote by hand. The reference locale is never
overwritten — it is the source, and a round trip must not rewrite the text
everything else is translated from.

**The HTTP contract in `LangSyncerClient` is inferred from the specification
and has not been exercised against the real service.** Everything around it --
the commands, the webhook, the signature check, the file writing -- does not
depend on those details; that one class is what changes when the real API is
known.

---

## Admin screen

`base-tenant.languages.index`, permission `languages.manage`. A **system**
screen, not a tenant one: the catalogue belongs to the installation, and one
customer switching Catalan off for everybody would be an odd amount of power.

---

## Anti-patterns

```php
// ✗ Needs a deploy to change, and the picker, the validator and the fallback
//   all end up with their own copy of the list.
$locales = config('app.available_locales');

// ✓
$locales = Language::codes();
```

```php
// ✗ Trusts a stored preference that may point at a withdrawn language.
app()->setLocale($user->locale);

// ✓ Already done by SetLocale, and resolved against what is enabled.
```
