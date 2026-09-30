<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Exceptions;

/**
 * Tenant-owned data was touched with no account in context and no explicit
 * bypass.
 *
 * Thrown instead of quietly returning nothing (`on_missing_tenant = deny`) or
 * quietly writing a row that belongs to nobody. A query that fails loudly is a
 * bug found in development; one that returns an empty list is a support ticket
 * about "my data disappeared".
 */
class MissingTenantException extends TenancyException
{
    public static function forQuery(string $model): self
    {
        return new self(sprintf(
            'No account is in context for a query on [%s]. Put one in context with Tenant::runFor(), '
            .'or bypass the scope explicitly with Tenant::runWithout() or ->acrossAccounts().',
            $model,
        ));
    }

    public static function forCreate(string $model): self
    {
        return new self(sprintf(
            'Cannot create [%s] without an account: none is in context and no account_id was given. '
            .'Create it inside Tenant::runFor(), or set account_id explicitly.',
            $model,
        ));
    }

    public static function forWrite(string $model, string $operation): self
    {
        return new self(sprintf(
            'Cannot %s [%s] with no account in context. Run it inside Tenant::runFor(), '
            .'or inside Tenant::runWithout() when crossing accounts is intended.',
            $operation,
            $model,
        ));
    }
}
