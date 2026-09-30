<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Exceptions;

/**
 * An account id was put in context but no such account exists.
 *
 * Without it `Tenant::set($id)` leaves the context empty, and whatever runs
 * next runs with no tenant -- which, depending on `on_missing_tenant`, means
 * unfiltered.
 */
class UnknownTenantException extends TenancyException
{
    public static function forId(string $accountId): self
    {
        return new self(sprintf('No account exists with id [%s]; refusing to run with an empty context.', $accountId));
    }
}
