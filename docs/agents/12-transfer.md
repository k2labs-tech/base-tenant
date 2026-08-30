# Transfer — imports and exports

**Module:** M3 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_TRANSFER_ENABLED` (on by default)

## When to use this

- Letting a customer bring data in from a spreadsheet.
- Letting them take data out as one.
- Any migration from a previous system that arrives as a file.

## When NOT to use this

- A machine-to-machine feed. That is an API or a connection, not a file.
- A report that is read on screen rather than downloaded — build a screen.
- Anything small enough to paste into a form.

---

## Declare a handler

```php
use Base\Tenant\Transfer\Import;

class GuestImport extends Import
{
    public function columns(): array
    {
        return [
            'name' => 'app::guests.name',      // translation keys
            'email' => 'app::guests.email',
            'phone' => 'app::guests.phone',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function persist(array $row): void
    {
        Guest::updateOrCreate(['email' => $row['email']], $row);
    }
}
```

```php
use Base\Tenant\Transfer\Export;

class GuestExport extends Export
{
    public function query(): Builder { return Guest::query(); }

    public function headings(): array { return ['app::guests.name', 'app::guests.email']; }

    public function map(Model $record): array { return [$record->name, $record->email]; }
}
```

Register both in `base-tenant.transfer.imports` / `exports`, keyed by the name
that appears in a URL.

`persist()` and `query()` run inside the account the transfer belongs to, so a
model using `BelongsToAccount` is stamped and filtered correctly with nothing
extra. This is explicit in the job, not inherited from the queue: a retried job
would otherwise store every row against no account at all.

---

## Running one

```php
use Base\Tenant\Facades\Transfer;

$mapping = Transfer::guessMapping($import, Csv::headers($path));
Transfer::import('guests', $uploadedFile, $mapping);

Transfer::export('guests');
```

Both return a `DataTransfer` immediately and queue the work. The row exists
from the moment the user asks, so an import waiting behind an hour of work
shows as queued rather than as nothing at all.

`required()` is derived from the validation rules: a field that is `required`
must be mapped before the import will start.

---

## What the import guarantees

**Partial success.** Rows that fail validation are collected, not fatal.
Refusing a 4,000-row file because one phone number is malformed means the
customer re-uploads eleven times; accepting the bad row silently means they
find out months later.

**The rejected rows come back as a file** with their original columns plus a
reason and a line number. The user corrects it and re-uploads the same file.

**A chunk that fails is retried row by row**, so one bad record does not
discard the 499 good ones sharing its transaction.

**Streamed, never loaded.** Rows are read through a generator and written
through one. An import file is small in testing and 200 MB in production.

**Semicolons and byte order marks are handled.** Spanish and French Excel write
semicolons; an unhandled BOM makes the first column match nothing. Both are the
usual reason a file "arrives empty".

Exports are written with a BOM on purpose: without it Excel opens a UTF-8 CSV
as Latin-1 and every accented name comes out wrong.

---

## Anti-patterns

```php
// ✗ Parses in the request. Times out, and holds the file in memory twice.
foreach (array_map('str_getcsv', file($path)) as $row) { ... }

// ✓
Transfer::import('guests', $file, $mapping);
```

```php
// ✗ One bad row, and the customer is told "import failed" with no detail.
DB::transaction(fn () => collect($rows)->each->save());

// ✓ Handled by the job: chunk, then row by row, then an error file.
```

```php
// ✗ Loads the whole table to export it.
Csv::write($path, $headings, Guest::all()->map(...));

// ✓ Return a Builder from query(); the job chunks it.
```

---

## Table

`data_transfers` — `type`, `handler`, `status`, `name`, `file_id`,
`error_file_id`, `total_rows`, `processed_rows`, `failed_rows`, `mapping`,
`options`, `message`, timings, `created_by`.

Both files are rows in `files`, so they are metered, purged and downloaded
through the same path as everything else.
