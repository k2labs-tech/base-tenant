# Files — storage and uploads

**Module:** M2 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switch:** `BASE_TENANT_FILES_ENABLED` (on by default)

## When to use this

Any file a customer uploads: photos, documents, avatars, attachments. Whether
it belongs to a record or to the account at large.

## When NOT to use this

- Files the application generates and owns that no customer sees — a cache
  artefact, a compiled asset. Use the disk directly.
- Anything that must be streamed through the application for processing before
  it lands. This module's whole point is that the bytes never pass through PHP.

---

## Attach files to a model

```php
use Base\Tenant\Traits\HasFiles;
use Base\Tenant\Files\FileCollection;

class Property extends Model
{
    use HasFiles;

    public function fileCollections(): array
    {
        return [
            'photos' => FileCollection::images('photos', maxSize: 10 * 1024 * 1024),
            'deeds' => FileCollection::make('deeds', accepts: ['application/pdf']),
            'cover' => FileCollection::images('cover', single: true),
        ];
    }
}
```

```php
$property->files();                          // MorphMany, ordered
$property->filesIn('photos');
$property->firstFile('cover')?->variantUrl('thumb');
$property->filesSize('photos');              // bytes held by this record
```

A collection declares what it accepts, how big, whether it holds one file or
many, and which renditions are derived. Declaring it on the model rather than
at the call site means the answer to "may this file go here" is the same
whether it arrives from the uploader, an import or a command.

`FileCollection::images()` is the shortcut for the common case: image types
plus a `thumb` (200×200 cover) and a `preview` (1200 wide, contain).

---

## In a screen

```blade
<livewire:base-tenant.files.uploader :fileable="$property" collection="photos" />
<livewire:base-tenant.files.gallery :fileable="$property" collection="photos" />
<livewire:base-tenant.files.usage-badge />
```

The gallery listens for what the uploader finishes, so a file appears the
moment it exists. The badge reads the storage gauge — one row, not a scan.

For the account-wide library, route to `Base\Tenant\Livewire\FileLibrary`
(already registered at `base-tenant.files.index`).

---

## The upload flow

Four steps. Understanding them matters, because step three is where the
security lives.

1. **`POST /files/sign`** — the browser says what it is about to upload. The
   server checks the collection rules and the remaining quota, and returns a
   URL plus a key under `tmp/`.
2. **`PUT` to that URL** — the browser sends the bytes straight to the object
   store. They never pass through PHP. On Vapor this is the only way that
   works at all: Lambda caps a request body at 6 MB.
3. **`POST /files/finalize`** — the server inspects the object that actually
   landed: real size, MIME read from the bytes, quota re-checked, then the move
   from `tmp/` to its final path, the row, the meter increment and the queued
   variants.
4. **Lifecycle** — a bucket rule expires anything left in `tmp/`.

**Step three does not trust step one.** Everything the signature was based on
came from the browser. A client that claims a 2 KB PNG and uploads a 2 GB video
is caught here or not at all.

The MIME type is read from the bytes and never from `Content-Type`: an
executable announced as `image/png` would otherwise be served back with that
header to whoever opened it.

`finalize` refuses any key outside the `tmp/` prefix. It is reachable over HTTP
and moves whatever key it is given, so without that guard a caller could name
another account's object and have it moved into their own.

### Doing it from code

```php
use Base\Tenant\Files\FileStore;

$file = app(FileStore::class)->finalize(
    key: $temporaryKey,
    collection: $property->fileCollection('photos'),
    name: 'facade.jpg',
    fileable: $property,
    uploadedBy: Auth::id(),
);

app(FileStore::class)->delete($file);   // bytes, renditions and the gauge
```

---

## Paths and storage

`accounts/{account_id}/files/{file_id}/{name}`

One directory per file, so removing it removes every derived rendition without
needing a list of what was derived, and two files with the same name never
collide.

- `BASE_TENANT_FILES_DRIVER=vapor` — signed direct upload to S3. Needs
  `league/flysystem-aws-s3-v3`.
- `BASE_TENANT_FILES_DRIVER=local` — the application accepts the PUT itself.
  For development. The browser code is identical either way.

Files are soft-deleted; the bytes go with the GDPR purge once the retention
window passes.

---

## Metering

Uploads increment the `storage.bytes` gauge, and so do the derived renditions:
counting only originals would make the gauge disagree with the storage bill by
a factor that grows with every variant added.

`k2labs-base:reconcile-storage` recomputes the gauge from the table weekly.
Increments can be lost — a finalize that dies between the move and the meter, a
file removed straight from the bucket — and nobody should be billed on a drift.

---

## Anti-patterns

```php
// ✗ Pushes every byte through PHP twice, and on Vapor fails over 6 MB.
class Upload extends Component
{
    use WithFileUploads;
    public $photo;
}

// ✓
<livewire:base-tenant.files.uploader :fileable="$property" collection="photos" />
```

```php
// ✗ Trusts the browser about what this file is.
$mime = $request->file('doc')->getClientMimeType();

// ✓ FileStore reads it from the bytes.
```

```php
// ✗ A scan of every file the account ever uploaded, on every page load.
$used = $account->files()->sum('size');

// ✓
$used = Meter::for($account)->current('storage.bytes');
```

---

## Table

`files` — `account_id`, nullable `fileable` morph, `collection`, `disk`,
`path`, `name`, `extension`, `mime_type`, `size`, `checksum`, `variants` JSON,
`custom_properties` JSON, `order_column`, `uploaded_by`, soft deletes.

The morph is nullable on purpose: a media library holds files that belong to
the account and to nothing else in particular.
