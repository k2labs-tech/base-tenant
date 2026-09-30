<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Illuminate\Support\Facades\Route;

/**
 * Where a person lands once they are in: after signing in, passing the second
 * factor, confirming the password, verifying the address, accepting an
 * invitation or switching account.
 *
 * `home_url` holds a route name (the default, `base-tenant.dashboard`) or a
 * path such as `/app`. Every destination that used to name the dashboard by
 * hand reads it through here, so a host that points it somewhere else is
 * obeyed everywhere and not only on half of the screens.
 */
final class Home
{
    public const DEFAULT = 'base-tenant.dashboard';

    /**
     * The configured value, as written.
     */
    public static function destination(): string
    {
        $destination = config('base-tenant.home_url', self::DEFAULT);

        return is_string($destination) && $destination !== '' ? $destination : self::DEFAULT;
    }

    /**
     * The URL of the destination.
     *
     * A route name that is not registered — the default one with the
     * application routes switched off, typically — falls back to the site
     * root rather than failing the sign-in that was about to succeed.
     */
    public static function url(bool $absolute = true): string
    {
        $destination = self::destination();

        if (Route::has($destination)) {
            return route($destination, [], $absolute);
        }

        if (self::isUrl($destination)) {
            if (str_starts_with($destination, '/')) {
                return $absolute ? url($destination) : $destination;
            }

            return $destination;
        }

        return $absolute ? url('/') : '/';
    }

    /**
     * The URL of a named route when it is registered, and home otherwise.
     *
     * For the few destinations that belong to a route group an installation
     * may have switched off (the profile, the checkout, the public home).
     */
    public static function routeOrHome(string $name, bool $absolute = true): string
    {
        return Route::has($name) ? route($name, [], $absolute) : self::url($absolute);
    }

    private static function isUrl(string $destination): bool
    {
        return str_starts_with($destination, '/')
            || filter_var($destination, FILTER_VALIDATE_URL) !== false;
    }
}
