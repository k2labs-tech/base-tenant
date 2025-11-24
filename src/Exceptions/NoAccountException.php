<?php

namespace Base\Tenant\Exceptions;

use RuntimeException;

class NoAccountException extends RuntimeException
{
    public static function userHasNoAccounts(): self
    {
        return new self(
            'User has no accounts. Please contact support or complete onboarding.'
        );
    }
}
