<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Illuminate\Support\Facades\DB;

/**
 * How a search box has to be written to behave the same everywhere.
 */
class Search
{
    /**
     * `LIKE` ignores case on MySQL and SQLite and respects it on PostgreSQL,
     * so a search box written once finds different things depending on where
     * the product is installed: typing `alf` stops matching `Alfa`. PostgreSQL
     * spells the case-insensitive comparison `ILIKE`.
     */
    public static function operator(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';
    }
}
