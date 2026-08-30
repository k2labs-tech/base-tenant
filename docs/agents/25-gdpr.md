# GDPR

**Module:** Q6 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_GDPR_ENABLED` (on by default)

## When to use this

- Registering a domain of personal data so it appears in a subject access
  request.
- Answering a request for somebody's data.
- Making sure deleted data is actually deleted.

## When NOT to use this

- Ordinary soft deletes. Those already work; this is what eventually destroys
  them.
- Business data that happens to mention a person. The exporters are for data
  *about* the person.

---

## Register your domains

**This is the part that matters.** The package can only export what it is told
about, and a domain nobody registered is data that quietly does not appear in a
legal disclosure.

```php
use Base\Tenant\Gdpr\GdprExporter;

class BookingExporter implements GdprExporter
{
    public function name(): string { return 'bookings'; }

    public function export(Authenticatable $user): array
    {
        return Booking::where('guest_email', $user->email)->get()->toArray();
    }
}
```

Add it to `base-tenant.gdpr.exporters`. Profile and activity ship registered.

Never export a password hash or a two-factor secret. A hash is still a
credential, and an export is a file that travels.

---

## Exporting

```php
ExportUserData::dispatch($user->getKey());
```

```bash
php artisan k2labs-base:export-user-data ada@example.test
php artisan k2labs-base:export-user-data ada@example.test --path=/tmp/ada.zip
```

Produces a ZIP with one JSON per domain plus a manifest. One file per domain
rather than one object, so a person can find the part they care about, and so a
domain added later does not change the shape of everything else. The manifest
records which domains were asked and how many records each held — "empty" and
"never asked" are different answers to a legal request.

The user is emailed a link that works for 24 hours, not an attachment: a file
full of somebody's personal data should not sit in a mailbox forever.

`--path` is the form a lawyer asks for: the file, now, without a mail round
trip.

---

## Purging

```bash
php artisan k2labs-base:purge-deleted --dry-run
```

Runs daily. Destroys users and files whose soft delete is older than
`gdpr.retention_days`. Soft deletion is a grace period, not a filing system: a
record still there a year after somebody asked for it to go is a record the
product promised to delete and did not.

Activity is **anonymised, not deleted**: the causer is cleared and the
description kept. An audit trail without the person is still an audit trail,
and deleting it would destroy the record of what was done to other people's
data.

Files have their bytes removed before their row, because the other order leaves
an object on the disk that nothing points at and nobody bills.

---

## Terms

Set `BASE_TENANT_TERMS_VERSION`. Any user whose stored version differs is sent
to `base-tenant.terms` until they accept. Empty switches the middleware off.

Version and not date: proving somebody agreed is worth nothing without knowing
what they agreed to.

The accept screen keeps sign-out reachable. Requiring acceptance with no way to
leave is not consent.

---

## Anti-patterns

```php
// ✗ Exports whatever the model happens to hold, including the password hash
//   and the two-factor secret.
return $user->toArray();

// ✓ Name the fields.
```

```php
// ✗ Deletes the audit trail along with the person, destroying the record of
//   what was done to everyone else.
ActivityLog::where('causer_id', $user->id)->delete();

// ✓ Handled by the purge: causer cleared, description kept.
```
