<?php

declare(strict_types=1);

namespace Base\Tenant\Exceptions;

use RuntimeException;

class ModuleDisabledException extends RuntimeException
{
    public static function for(string $module): self
    {
        return new self(sprintf(
            'The `%s` module is disabled. Set BASE_TENANT_%s_ENABLED=true to use it.',
            $module,
            strtoupper($module),
        ));
    }
}
