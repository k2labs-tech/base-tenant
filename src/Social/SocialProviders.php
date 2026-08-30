<?php

declare(strict_types=1);

namespace Base\Tenant\Social;

use InvalidArgumentException;

/**
 * The providers the package knows how to talk to, and which of them this
 * installation actually offers.
 *
 * A provider shows up on the login screen when its credentials are present in
 * `config/services.php`, and never because a second flag says so. Two switches
 * for one fact is two things to get out of step, and the failure mode -- a
 * button that leads to a provider error page -- is the worst kind: it looks
 * like the product is broken.
 */
final class SocialProviders
{
    /**
     * Provider key => the Socialite driver behind it.
     *
     * LinkedIn is `linkedin-openid` and not `linkedin`: the old driver uses an
     * API LinkedIn has retired, and the two are not interchangeable.
     */
    public const DRIVERS = [
        'google' => 'google',
        'linkedin' => 'linkedin-openid',
        'microsoft' => 'microsoft',
    ];

    /** Brand marks, so the buttons look like the buttons people expect. */
    public const LABELS = [
        'google' => 'Google',
        'linkedin' => 'LinkedIn',
        'microsoft' => 'Microsoft',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::DRIVERS);
    }

    public static function supports(string $provider): bool
    {
        return array_key_exists($provider, self::DRIVERS);
    }

    public static function driver(string $provider): string
    {
        return self::DRIVERS[$provider] ?? throw new InvalidArgumentException(
            "`{$provider}` is not a social provider this package supports."
        );
    }

    public static function label(string $provider): string
    {
        return self::LABELS[$provider] ?? ucfirst($provider);
    }

    /**
     * Is this provider configured on this installation?
     */
    public static function configured(string $provider): bool
    {
        if (! self::supports($provider)) {
            return false;
        }

        $service = config('services.'.self::driver($provider));

        return is_array($service)
            && ! empty($service['client_id'])
            && ! empty($service['client_secret']);
    }

    /**
     * The providers to actually put on the screen.
     *
     * @return list<string>
     */
    public static function enabled(): array
    {
        if (! config('base-tenant.social.enabled', true)) {
            return [];
        }

        return array_values(array_filter(self::all(), self::configured(...)));
    }

    /**
     * B2B installations often want sign-up limited to their customers'
     * domains. An empty list means anyone.
     */
    public static function allowsEmail(?string $email): bool
    {
        $domains = config('base-tenant.social.allowed_domains', []);

        if ($domains === [] || $email === null) {
            return $domains === [];
        }

        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array($domain, array_map('strtolower', $domains), true);
    }
}
