<?php

declare(strict_types=1);

namespace Base\Tenant\Transfer;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of export.
 *
 *     class GuestExport extends Export
 *     {
 *         public function query(): Builder
 *         {
 *             return Guest::query()->with('bookings');
 *         }
 *
 *         public function headings(): array
 *         {
 *             return ['Name', 'Email', 'Bookings'];
 *         }
 *
 *         public function map(Model $record): array
 *         {
 *             return [$record->name, $record->email, $record->bookings->count()];
 *         }
 *     }
 *
 * The query runs inside the account the transfer belongs to, so a model using
 * `BelongsToAccount` exports that account's rows and no others.
 */
abstract class Export
{
    /**
     * What to export. Not a collection: the rows are streamed in chunks, and
     * returning everything at once is the one thing an export must not do.
     */
    abstract public function query(): Builder;

    /**
     * @return list<string>
     */
    abstract public function headings(): array;

    /**
     * @return array<int, mixed>
     */
    abstract public function map(Model $record): array;

    public function chunkSize(): int
    {
        return 1000;
    }

    public function label(): string
    {
        return static::class;
    }

    /**
     * The file name offered to the user, without extension.
     */
    public function filename(): string
    {
        return 'export-'.now()->format('Y-m-d-His');
    }
}
