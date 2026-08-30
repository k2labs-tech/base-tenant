<?php

declare(strict_types=1);

namespace Base\Tenant\Connections;

use Base\Tenant\Models\AccountConnection;

/**
 * The outcome of asking a provider whether a connection still works.
 */
final class HealthCheck
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
    ) {}

    public static function healthy(?string $message = null): self
    {
        return new self(AccountConnection::HEALTHY, $message);
    }

    public static function failing(string $message): self
    {
        return new self(AccountConnection::FAILING, $message);
    }

    /**
     * The provider could not be reached at all.
     *
     * Deliberately not `failing`: the credentials may be perfectly good and
     * the service simply down, and telling a customer their credentials are
     * broken when they are not is worse than saying nothing.
     */
    public static function unknown(string $message): self
    {
        return new self(AccountConnection::UNKNOWN, $message);
    }

    public function isHealthy(): bool
    {
        return $this->status === AccountConnection::HEALTHY;
    }
}
