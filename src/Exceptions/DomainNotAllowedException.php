<?php

declare(strict_types=1);

namespace Base\Tenant\Exceptions;

use RuntimeException;

/**
 * An address outside the domains the account accepts.
 *
 * Named for what it is rather than reusing a validation exception, so the
 * screen can catch this specific refusal and say which domains are allowed.
 */
class DomainNotAllowedException extends RuntimeException
{
    /**
     * @param  array<int, string>  $allowed
     */
    public static function for(string $email, array $allowed): self
    {
        return new self(__('base-tenant::security.email_domain_not_allowed', [
            'domains' => implode(', ', $allowed),
        ]));
    }
}
