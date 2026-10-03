<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Files\ActiveContent;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use RuntimeException;
use Throwable;

/**
 * One stored file.
 *
 * Soft deleted rather than destroyed on delete: the bytes are removed by the
 * GDPR purge once the retention window has passed, so a mistaken delete is
 * recoverable for as long as the product promises it is.
 */
class File extends Model
{
    use BelongsToAccount;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'order_column' => 'integer',
            'variants' => 'array',
            'custom_properties' => 'array',
        ];
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.user', User::class),
            'uploaded_by'
        );
    }

    public function scopeInCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('created_at');
    }

    public function storage(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * A link that works for as long as it is needed and no longer.
     *
     * Files live on a private disk: an account's documents are not public
     * because the URL is hard to guess. Drivers that cannot sign fall back to
     * the streaming route, which authorises on every request -- unless
     * `files.stream_fallback` is off, for an application that has promised
     * every link it hands out expires. Then this throws instead of quietly
     * returning a link that does not.
     *
     * @throws RuntimeException when the disk cannot sign and the fallback is off
     */
    public function url(int $minutes = 5): string
    {
        return $this->temporaryUrlOrNull($minutes)
            ?? $this->streamUrl(['file' => $this->getKey()]);
    }

    public function variantUrl(string $variant, int $minutes = 5): ?string
    {
        if (($this->variants[$variant] ?? null) === null) {
            return null;
        }

        return $this->temporaryUrlOrNull($minutes, $variant)
            ?? $this->streamUrl(['file' => $this->getKey(), 'variant' => $variant]);
    }

    /**
     * A signed, expiring URL, or null when the disk cannot sign one.
     *
     * Never the streaming route, whatever `files.stream_fallback` says: this
     * is the call for code that needs to know it got a link with an expiry,
     * and would rather handle the absence than be handed something else.
     */
    public function temporaryUrlOrNull(int $minutes = 5, ?string $variant = null): ?string
    {
        $path = $variant === null ? $this->path : ($this->variants[$variant] ?? null);

        if ($path === null) {
            return null;
        }

        // A signed URL cannot carry a CSP, so active content (an SVG, an HTML
        // page) is asked to arrive as a download instead of being rendered.
        // S3 honours the override; disks that cannot simply ignore it.
        $options = $variant === null && ActiveContent::is($this->mime_type)
            ? ['ResponseContentDisposition' => 'attachment']
            : [];

        try {
            return $this->storage()->temporaryUrl($path, now()->addMinutes($minutes), $options);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Does this file belong to a collection declared `public`?
     *
     * The rules are looked up where they are declared -- the owning model's
     * `fileCollections()`, or `files.collections` for the account library --
     * and never stored on the row, so turning a collection private again
     * takes effect for every file already in it.
     */
    public function isPublic(): bool
    {
        return $this->collectionRules()?->public === true;
    }

    /**
     * A stable address for a file anyone may see, or null for the rest.
     *
     * It does not expire and needs no session: it is meant to be embedded, as
     * the logo of a status page is. The route streams the file only while its
     * collection is still public and the file still exists, so the address is
     * stable without being a standing grant.
     */
    public function publicUrl(?string $variant = null): ?string
    {
        if (! $this->isPublic()) {
            return null;
        }

        if ($variant !== null && ($this->variants[$variant] ?? null) === null) {
            return null;
        }

        return route('base-tenant.files.public', array_filter([
            'file' => $this->getKey(),
            'variant' => $variant,
        ]));
    }

    /**
     * The rules of the collection this file was stored under, or null when
     * nothing declares it any more.
     */
    public function collectionRules(): ?FileCollection
    {
        if ($this->fileable_type !== null && $this->fileable_id !== null) {
            return $this->ownerCollection();
        }

        $rules = config("base-tenant.files.collections.{$this->collection}");

        return $rules === null ? null : FileCollection::fromConfig($this->collection, $rules);
    }

    /**
     * Asked of the owning record, found without the tenant scope: the public
     * route runs with no account in context, and the file's own account is
     * the only one the owner can be in anyway.
     */
    protected function ownerCollection(): ?FileCollection
    {
        $class = Model::getActualClassNameForMorph($this->fileable_type);

        if (! class_exists($class)) {
            return null;
        }

        $owner = (new $class)->newQueryWithoutScopes()->find($this->fileable_id);

        if (! $owner || ! method_exists($owner, 'fileCollection')) {
            return null;
        }

        try {
            return $owner->fileCollection($this->collection);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $parameters
     *
     * @throws RuntimeException when the fallback is switched off
     */
    protected function streamUrl(array $parameters): string
    {
        if (! config('base-tenant.files.stream_fallback', true)) {
            throw new RuntimeException(sprintf(
                'Disk `%s` cannot sign temporary URLs and `files.stream_fallback` is off.',
                $this->disk,
            ));
        }

        return route('base-tenant.files.show', $parameters);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    /**
     * Everything this file occupies on the disk, its renditions included.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return [$this->path, ...array_values($this->variants ?? [])];
    }

    /**
     * Bytes the renditions occupy on the disk right now.
     *
     * Read from the disk rather than stored, so a rendition that was never
     * written, or was already removed, counts as nothing.
     */
    public function variantsSize(): int
    {
        $bytes = 0;

        foreach (array_unique(array_values($this->variants ?? [])) as $path) {
            if (! is_string($path) || $path === $this->path) {
                continue;
            }

            // One request per rendition: a missing object throws rather than
            // being asked about twice.
            try {
                $bytes += (int) $this->storage()->size($path);
            } catch (Throwable) {
                continue;
            }
        }

        return $bytes;
    }

    /**
     * The directory every copy of this file lives in.
     *
     * Files get a directory of their own rather than a shared folder, so
     * removing one is removing a directory and no rendition can be orphaned by
     * a name that was not on the list.
     */
    public function directory(): string
    {
        return dirname($this->path);
    }
}
