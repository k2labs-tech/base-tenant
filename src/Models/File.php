<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

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
     * the streaming route, which authorises on every request.
     */
    public function url(int $minutes = 5): string
    {
        try {
            return $this->storage()->temporaryUrl($this->path, now()->addMinutes($minutes));
        } catch (\RuntimeException) {
            return route('base-tenant.files.show', ['file' => $this->getKey()]);
        }
    }

    public function variantUrl(string $variant, int $minutes = 5): ?string
    {
        $path = $this->variants[$variant] ?? null;

        if ($path === null) {
            return null;
        }

        try {
            return $this->storage()->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\RuntimeException) {
            return route('base-tenant.files.show', ['file' => $this->getKey(), 'variant' => $variant]);
        }
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
