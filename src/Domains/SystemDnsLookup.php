<?php

declare(strict_types=1);

namespace Base\Tenant\Domains;

use Base\Tenant\Domains\Contracts\DnsLookup;

/**
 * The resolver the machine already has.
 */
class SystemDnsLookup implements DnsLookup
{
    /**
     * @return list<string>
     */
    public function txt(string $host): array
    {
        // `dns_get_record` emits a warning and returns false for a name that
        // does not resolve. That is the normal answer while a customer is
        // still editing their DNS, so it is silenced rather than surfaced.
        $records = @dns_get_record($host, DNS_TXT);

        if ($records === false) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            // Long values arrive split into chunks; `entries` holds the parts
            // and `txt` the joined string. Only one of them is guaranteed.
            if (isset($record['entries']) && is_array($record['entries'])) {
                $values[] = implode('', $record['entries']);

                continue;
            }

            if (isset($record['txt']) && is_string($record['txt'])) {
                $values[] = $record['txt'];
            }
        }

        return array_values(array_filter($values));
    }
}
