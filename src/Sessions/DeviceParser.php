<?php

declare(strict_types=1);

namespace Base\Tenant\Sessions;

/**
 * A readable name for a user agent string.
 *
 * Deliberately approximate, and small. The screen's job is to let somebody
 * recognise "that is my phone" or "that is not mine" -- it is not device
 * fingerprinting, and a wrong browser name costs nothing. A dependency that
 * parses user agents properly would be several thousand lines and a monthly
 * data update for that.
 */
final class DeviceParser
{
    /**
     * @return array{device: string|null, browser: string|null, platform: string|null}
     */
    public static function parse(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return ['device' => null, 'browser' => null, 'platform' => null];
        }

        return [
            'device' => self::device($userAgent),
            'browser' => self::browser($userAgent),
            'platform' => self::platform($userAgent),
        ];
    }

    protected static function device(string $agent): string
    {
        if (preg_match('/\b(iPad|Tablet)\b/i', $agent)) {
            return 'tablet';
        }

        if (preg_match('/\b(Mobile|iPhone|Android|iPod)\b/i', $agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected static function browser(string $agent): ?string
    {
        // Order matters: Edge and Opera both claim to be Chrome, and Chrome
        // claims to be Safari. Checking the impostors first is the whole trick.
        return match (true) {
            (bool) preg_match('/Edg[e\/]/i', $agent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $agent) => 'Opera',
            (bool) preg_match('/Firefox\//i', $agent) => 'Firefox',
            (bool) preg_match('/Chrome\//i', $agent) => 'Chrome',
            (bool) preg_match('/Safari\//i', $agent) => 'Safari',
            default => null,
        };
    }

    protected static function platform(string $agent): ?string
    {
        return match (true) {
            (bool) preg_match('/Windows NT/i', $agent) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $agent) => 'iOS',
            (bool) preg_match('/Mac OS X/i', $agent) => 'macOS',
            (bool) preg_match('/Android/i', $agent) => 'Android',
            (bool) preg_match('/Linux/i', $agent) => 'Linux',
            default => null,
        };
    }
}
