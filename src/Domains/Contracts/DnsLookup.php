<?php

declare(strict_types=1);

namespace Base\Tenant\Domains\Contracts;

/**
 * Reads TXT records for a hostname.
 *
 * It is an interface for one reason: domain verification is untestable
 * otherwise. A test binds a fake, an installation behind a split-horizon
 * resolver binds its own.
 */
interface DnsLookup
{
    /**
     * Every TXT value published at the given host, or an empty array.
     *
     * Implementations must not throw on a hostname that does not resolve --
     * that is an ordinary answer, not an error.
     *
     * @return list<string>
     */
    public function txt(string $host): array;
}
