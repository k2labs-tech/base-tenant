<?php

declare(strict_types=1);

namespace Base\Tenant\Files;

use Base\Tenant\Exceptions\UsageLimitExceededException;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Jobs\GenerateFileVariants;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uploads, in four steps.
 *
 *   1. `sign()`   the browser asks where to put the bytes and is told
 *   2. PUT        the browser sends them straight to the disk, never through
 *                 the application
 *   3. `finalize()` the application inspects what actually landed and records
 *                 it
 *   4. lifecycle  whatever was never finalised expires out of `tmp/`
 *
 * Step 3 does not trust step 1. The size, the type and the room left are read
 * again from the object that exists, because everything step 1 was told came
 * from the browser: a client that claims a 2 KB PNG and uploads a 2 GB video
 * has to be caught after the fact, and this is the only place that can.
 */
class FileStore
{
    /**
     * How long a signed upload URL stays valid. Long enough for a large file
     * on a poor connection, short enough that a leaked URL is not a standing
     * write grant.
     */
    public const SIGNATURE_MINUTES = 30;

    /**
     * Where unfinalised uploads land. A bucket lifecycle rule expires this
     * prefix; nothing here is ever read after the move.
     */
    public const TMP_PREFIX = 'tmp';

    public const METRIC = 'storage.bytes';

    /**
     * Tell the browser where to put the bytes.
     *
     * The checks here are advisory: they stop the obvious mistake before a
     * large upload starts, and they are all made again in `finalize()` against
     * what really arrived.
     *
     * @return array{uuid: string, key: string, url: string, headers: array<string, string>, expires: string}
     */
    public function sign(FileCollection $collection, string $name, string $mimeType, int $size, ?Account $account = null): array
    {
        Module::ensure(Module::FILES);

        $account = $this->account($account);

        $collection->assertAccepts($mimeType, $size);

        $this->assertRoom($account, $size);

        $uuid = (string) Str::uuid();
        $key = self::TMP_PREFIX.'/'.$uuid;

        return [
            'uuid' => $uuid,
            'key' => $key,
            'url' => $this->uploadUrl($key, $mimeType),
            'headers' => ['Content-Type' => $mimeType],
            'expires' => now()->addMinutes(self::SIGNATURE_MINUTES)->toIso8601String(),
        ];
    }

    /**
     * Record what actually landed.
     *
     * @param  array<string, mixed>  $properties
     *
     * @throws RuntimeException when nothing arrived at the signed key
     * @throws UsageLimitExceededException when the real size does not fit
     */
    public function finalize(
        string $key,
        FileCollection $collection,
        string $name,
        ?Model $fileable = null,
        ?Account $account = null,
        array $properties = [],
        ?string $uploadedBy = null,
    ): File {
        Module::ensure(Module::FILES);

        $account = $this->account($account);
        $disk = $this->disk();

        if (! $this->isTemporaryKey($key) || ! $disk->exists($key)) {
            throw new RuntimeException("Nothing was uploaded to `{$key}`.");
        }

        // Read from the object, not from what the browser said about it.
        $size = (int) $disk->size($key);
        $mimeType = $this->sniff($disk, $key);

        try {
            $collection->assertAccepts($mimeType, $size);

            // The authoritative check. The one in `sign()` was made against a
            // number the client chose.
            $this->assertRoom($account, $size);
        } catch (\Throwable $exception) {
            // Whatever is refused does not sit in `tmp/` until the lifecycle
            // rule gets to it, taking up space nobody is accountable for.
            $disk->delete($key);

            throw $exception;
        }

        $file = new File([
            'account_id' => $account->getKey(),
            'collection' => $collection->name,
            'disk' => $this->diskName(),
            'name' => $this->safeName($name),
            'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: null,
            'mime_type' => $mimeType,
            'size' => $size,
            'checksum' => $this->checksum($disk, $key),
            'custom_properties' => $properties ?: null,
            'uploaded_by' => $uploadedBy,
            'order_column' => $this->nextOrder($account, $collection, $fileable),
        ]);

        $file->setAttribute('id', (string) Str::uuid());

        if ($fileable) {
            $file->fileable_type = $fileable->getMorphClass();
            $file->fileable_id = $fileable->getKey();
        }

        $file->path = $this->pathFor($account, $file);

        // The move is last before the row, so a failure here leaves the
        // temporary object to expire rather than a row pointing at nothing.
        $disk->move($key, $file->path);

        // A collection that holds one file replaces rather than accumulates.
        // Doing it before the save keeps the invariant true at every instant a
        // reader could look.
        if ($collection->single) {
            $this->clearCollection($account, $collection->name, $fileable);
        }

        $file->save();

        $this->meterUp($account, $size, $file);

        if ($collection->variants !== [] && $file->isImage()) {
            GenerateFileVariants::dispatch($file->getKey(), $collection->variants);
        }

        return $file;
    }

    /**
     * Remove a file, its renditions and the space it occupied.
     */
    public function delete(File $file, bool $keepBytes = false): void
    {
        $size = $file->size;
        $account = $file->account;

        if (! $keepBytes) {
            $file->storage()->deleteDirectory($file->directory());
        }

        $file->delete();

        if ($account) {
            $this->meterDown($account, $size, $file);
        }
    }

    /**
     * `accounts/{account}/files/{file}/{name}`.
     *
     * One directory per file, so removing it removes every rendition without
     * needing a list of what was derived -- and so that two files with the
     * same name never collide.
     */
    public function pathFor(Account $account, File $file): string
    {
        return sprintf(
            'accounts/%s/files/%s/%s',
            $account->getKey(),
            $file->getKey(),
            $file->name,
        );
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('base-tenant.files.disk', 's3');
    }

    public function driver(): string
    {
        return (string) config('base-tenant.files.driver', 'vapor');
    }

    /**
     * Guard against a key from outside the temporary prefix.
     *
     * `finalize()` is reachable over HTTP and moves whatever key it is given.
     * Without this, a caller could name any object on the disk -- another
     * account's file among them -- and have it moved into their own.
     */
    public function isTemporaryKey(string $key): bool
    {
        return str_starts_with($key, self::TMP_PREFIX.'/')
            && ! str_contains($key, '..');
    }

    /**
     * Where the browser PUTs the bytes.
     *
     * With `driver=local` this is a route in the application rather than a
     * signed object-store URL, which keeps the browser code identical between
     * development and production: one upload path, two back ends.
     */
    protected function uploadUrl(string $key, string $mimeType): string
    {
        if ($this->driver() === 'local') {
            return route('base-tenant.files.upload', ['key' => basename($key)]);
        }

        return $this->disk()->temporaryUploadUrl(
            $key,
            now()->addMinutes(self::SIGNATURE_MINUTES),
            ['ContentType' => $mimeType],
        )['url'];
    }

    /**
     * The type according to the bytes.
     *
     * The browser's `Content-Type` is a claim, and an `.exe` announced as
     * `image/png` would otherwise be served back with that header to whoever
     * opens it. Only the head of the object is read: the signature lives in
     * the first few bytes and a 100 MB download to identify one would defeat
     * the point of uploading straight to the disk.
     */
    protected function sniff(Filesystem $disk, string $key): string
    {
        $stream = $disk->readStream($key);

        if ($stream === null || $stream === false) {
            return 'application/octet-stream';
        }

        $head = fread($stream, 4096) ?: '';
        fclose($stream);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mimeType = finfo_buffer($finfo, $head);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * The object store's own checksum where there is one.
     *
     * Hashing the file ourselves would mean streaming every byte through the
     * application, which is exactly what the direct upload exists to avoid.
     */
    protected function checksum(Filesystem $disk, string $key): ?string
    {
        try {
            return $disk->checksum($key) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function assertRoom(Account $account, int $size): void
    {
        if (! Module::enabled(Module::METERING)) {
            return;
        }

        if (Meter::for($account)->wouldExceed(self::METRIC, $size)) {
            throw UsageLimitExceededException::for(
                Meter::metrics()->get(self::METRIC),
                Meter::for($account)->current(self::METRIC),
                Meter::for($account)->limit(self::METRIC),
                $account,
            );
        }
    }

    protected function meterUp(Account $account, int $size, File $file): void
    {
        if (Module::enabled(Module::METERING)) {
            Meter::for($account)->increment(self::METRIC, $size, ['subject' => $file]);
        }
    }

    protected function meterDown(Account $account, int $size, File $file): void
    {
        if (Module::enabled(Module::METERING)) {
            Meter::for($account)->decrement(self::METRIC, $size, ['subject' => $file]);
        }
    }

    protected function clearCollection(Account $account, string $collection, ?Model $fileable): void
    {
        $query = File::query()
            ->forAccount($account)
            ->inCollection($collection);

        $fileable
            ? $query->where('fileable_type', $fileable->getMorphClass())->where('fileable_id', $fileable->getKey())
            : $query->whereNull('fileable_id');

        $query->get()->each(fn (File $existing) => $this->delete($existing));
    }

    protected function nextOrder(Account $account, FileCollection $collection, ?Model $fileable): int
    {
        $query = File::query()
            ->forAccount($account)
            ->inCollection($collection->name);

        if ($fileable) {
            $query->where('fileable_type', $fileable->getMorphClass())
                ->where('fileable_id', $fileable->getKey());
        }

        return ((int) $query->max('order_column')) + 1;
    }

    /**
     * Keep the name recognisable, strip anything that could act as a path.
     */
    protected function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }

    protected function account(?Account $account): Account
    {
        return $account
            ?? Tenant::current()
            ?? throw new RuntimeException('Files need an account in context.');
    }
}
