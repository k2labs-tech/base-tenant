<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

/**
 * Does an address fall inside an entry of an allowlist?
 *
 * An entry is either a plain address or CIDR notation. Written by hand rather
 * than pulled in as a dependency because it is thirty lines and the alternative
 * is a package in the critical path of every request.
 */
final class IpRange
{
    /**
     * @param  array<int, string>  $allowlist
     */
    public static function matchesAny(string $ip, array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            if (self::matches($ip, trim($entry))) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $ip, string $entry): bool
    {
        if ($entry === '' || $ip === '') {
            return false;
        }

        if (! str_contains($entry, '/')) {
            return $ip === $entry;
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        if (! is_numeric($bits)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        // Different families -- an IPv4 address against an IPv6 range -- are
        // simply not a match, not an error.
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes > 0 && strncmp($ipBinary, $subnetBinary, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask);
    }

    /**
     * Is this a usable allowlist entry? Used to reject typos before they are
     * saved, because a bad entry in an enforcing list is a lockout.
     */
    public static function isValidEntry(string $entry): bool
    {
        $entry = trim($entry);

        if ($entry === '') {
            return false;
        }

        if (! str_contains($entry, '/')) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        if (filter_var($subnet, FILTER_VALIDATE_IP) === false || ! ctype_digit($bits)) {
            return false;
        }

        $max = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $bits >= 0 && (int) $bits <= $max;
    }
}
