<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Does an address fall inside an entry of an allowlist?
 *
 * An entry is either a plain address or CIDR notation. The comparison itself
 * is Symfony's `IpUtils`, already in the critical path of every request; what
 * this class adds is the normalisation that makes two spellings of the same
 * address compare equal. `2001:0DB8::0001` and `2001:db8::1` are one address,
 * and `::ffff:203.0.113.4` is what some servers hand over for `203.0.113.4`.
 * Comparing the raw strings locks out an office whose administrator typed the
 * address the way their router prints it.
 */
final class IpRange
{
    /** The 96-bit prefix of an IPv4-mapped IPv6 address, RFC 4291 §2.5.5.2. */
    private const MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

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
        $ip = self::normalizeAddress(trim($ip));
        $entry = self::normalizeEntry(trim($entry));

        if ($ip === null || $entry === null) {
            return false;
        }

        return IpUtils::checkIp($ip, $entry);
    }

    /**
     * Is this a usable allowlist entry? Used to reject typos before they are
     * saved, because a bad entry in an enforcing list is a lockout.
     *
     * The same grammar `matches()` uses, on purpose: an entry the screen
     * accepts but the matcher can never satisfy is a lockout with no error.
     */
    public static function isValidEntry(string $entry): bool
    {
        return self::normalizeEntry(trim($entry)) !== null;
    }

    /**
     * One spelling per address: compressed, lower-case, and an IPv4-mapped
     * IPv6 address unwrapped to the IPv4 it stands for. Null when the text is
     * not an address at all.
     */
    private static function normalizeAddress(string $address): ?string
    {
        if ($address === '') {
            return null;
        }

        $binary = @inet_pton($address);

        if ($binary === false) {
            return null;
        }

        if (self::isMapped($binary)) {
            $binary = substr($binary, 12);
        }

        $text = @inet_ntop($binary);

        return $text === false ? null : $text;
    }

    /**
     * The same, for an entry that may carry a prefix length. A mapped range
     * such as `::ffff:203.0.113.0/120` becomes `203.0.113.0/24`.
     */
    private static function normalizeEntry(string $entry): ?string
    {
        if (! str_contains($entry, '/')) {
            return self::normalizeAddress($entry);
        }

        [$address, $bits] = explode('/', $entry, 2);

        if (! ctype_digit($bits)) {
            return null;
        }

        $binary = @inet_pton($address);

        if ($binary === false) {
            return null;
        }

        $bits = (int) $bits;

        if (self::isMapped($binary)) {
            // A mapped range narrower than the 96-bit prefix would span
            // addresses that are not IPv4 at all; nothing this product sees
            // can ever fall inside one.
            if ($bits < 96) {
                return null;
            }

            $binary = substr($binary, 12);
            $bits -= 96;
        }

        if ($bits > strlen($binary) * 8) {
            return null;
        }

        $text = @inet_ntop($binary);

        return $text === false ? null : "{$text}/{$bits}";
    }

    private static function isMapped(string $binary): bool
    {
        return strlen($binary) === 16 && str_starts_with($binary, self::MAPPED_PREFIX);
    }
}
