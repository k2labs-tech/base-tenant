<?php

declare(strict_types=1);

namespace Base\Tenant\Exceptions;

use RuntimeException;

/**
 * An invitation that cannot be accepted by the person trying to.
 *
 * Translated because it reaches the screen: the person clicked a link and
 * deserves to be told why nothing happened.
 */
class InvitationException extends RuntimeException
{
    public static function forAnotherAddress(): self
    {
        return new self(__('base-tenant::invitations.for_another_address'));
    }
}
