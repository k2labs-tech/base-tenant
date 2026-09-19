<?php

declare(strict_types=1);

namespace Base\Tenant\Domains;

use Base\Tenant\Domains\Contracts\DnsLookup;
use Base\Tenant\Models\AccountDomain;

/**
 * Proof that the customer controls the name they claimed.
 *
 * The check is a TXT record they can only publish if they hold the zone. It is
 * the whole reason a custom domain is safe to serve: without it, pointing a
 * CNAME at the product would be enough to be served as somebody else.
 */
class DomainVerifier
{
    public function __construct(protected DnsLookup $dns) {}

    public function verify(AccountDomain $domain): bool
    {
        $expected = $domain->expectedRecord();

        foreach ($this->dns->txt($expected['host']) as $value) {
            // Providers wrap TXT values in quotes and pad them; compare the
            // trimmed strings rather than the raw ones.
            if (trim($value, " \t\n\r\0\x0B\"") === $expected['value']) {
                return true;
            }
        }

        return false;
    }
}
