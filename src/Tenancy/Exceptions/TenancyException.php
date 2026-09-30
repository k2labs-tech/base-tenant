<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Exceptions;

use RuntimeException;

/**
 * Parent of every exception the tenancy layer throws, so a host can catch the
 * whole family in one place -- a reporter, a queue failure handler -- without
 * listing each one.
 */
abstract class TenancyException extends RuntimeException {}
