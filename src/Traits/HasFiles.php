<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Files\FileCollection;
use Base\Tenant\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Attach files to a model.
 *
 *     class Property extends Model
 *     {
 *         use HasFiles;
 *
 *         public function fileCollections(): array
 *         {
 *             return [
 *                 'photos' => FileCollection::images('photos', maxSize: 10 * 1024 * 1024),
 *                 'deeds' => FileCollection::make('deeds', accepts: ['application/pdf']),
 *             ];
 *         }
 *     }
 *
 *     $property->filesIn('photos');
 *     $property->firstFile('photos')?->variantUrl('thumb');
 */
trait HasFiles
{
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable')->ordered();
    }

    public function filesIn(string $collection): MorphMany
    {
        return $this->files()->inCollection($collection);
    }

    public function firstFile(string $collection = 'default'): ?File
    {
        return $this->filesIn($collection)->first();
    }

    /**
     * The collections this model accepts. Override it; the default exists so
     * that a model can use the trait for a single unremarkable attachment
     * without declaring anything.
     *
     * @return array<string, FileCollection>
     */
    public function fileCollections(): array
    {
        return ['default' => FileCollection::make('default')];
    }

    /**
     * @throws InvalidArgumentException when the collection is not declared
     */
    public function fileCollection(string $name): FileCollection
    {
        return $this->fileCollections()[$name] ?? throw new InvalidArgumentException(sprintf(
            'Model %s does not declare a `%s` file collection. Declared: %s.',
            static::class,
            $name,
            implode(', ', array_keys($this->fileCollections())) ?: 'none',
        ));
    }

    /**
     * Total bytes held by this record. Not the account's usage -- that is a
     * meter, and asking the meter is a read of one row rather than a scan.
     */
    public function filesSize(?string $collection = null): int
    {
        return (int) ($collection === null ? $this->files() : $this->filesIn($collection))->sum('size');
    }
}
