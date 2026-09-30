<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Exceptions;

/**
 * A query builder method that reaches the table without the account scope and
 * cannot be given one.
 *
 * Eloquent forwards `truncate()`, `updateOrInsert()` and `updateFrom()` to the
 * base query builder, which never applies global scopes. Rather than let them
 * touch every account's rows, strict tenancy refuses them while the scope is
 * active. Removing the scope in writing -- `->acrossAccounts()` or
 * `Tenant::runWithout()` -- is the explicit, audited way through.
 */
class UnscopableQueryException extends TenancyException
{
    public static function forMethod(string $model, string $method): self
    {
        return new self(sprintf(
            '[%s::%s()] bypasses the account scope and cannot be constrained to one account. '
            .'Use a scoped alternative (updateOrCreate(), delete()), or call it through ->acrossAccounts() '
            .'or inside Tenant::runWithout() when touching every account is intended.',
            $model,
            $method,
        ));
    }

    /**
     * @param  array<int, string>  $uniqueBy
     */
    public static function forUpsertConflictTarget(string $model, string $column, array $uniqueBy): self
    {
        return new self(sprintf(
            'upsert() on [%s] must include [%s] in its conflict target; [%s] can match a row of another '
            .'account and overwrite it.',
            $model,
            $column,
            implode(', ', $uniqueBy),
        ));
    }
}
