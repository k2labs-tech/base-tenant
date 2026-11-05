<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The mask between how a moment is stored and how a person reads it.
 *
 * A product that stores its timestamps in UTC still has to show them in the
 * reader's clock: `$date->userTime()` returns the same moment in the
 * timezone of the person signed in (their `timezone` preference), else in
 * `base-tenant.display_timezone`, else in `app.timezone`. With the app and
 * every user on the same timezone it changes nothing, so a product that
 * stores local time keeps reading the same.
 *
 * Pass the person explicitly where nobody is signed in (a queued mail to a
 * notifiable): `$date->userTime($notifiable)`.
 */
final class UserTime
{
    /** @var array<string, true>|null */
    private static ?array $known = null;

    public static function register(): void
    {
        $macro = function (?object $user = null): CarbonInterface {
            /** @var CarbonInterface $this */
            return $this->copy()->setTimezone(UserTime::timezoneFor($user ?? auth()->user()));
        };

        Carbon::macro('userTime', $macro);
        CarbonImmutable::macro('userTime', $macro);
    }

    /** The timezone a person reads in; one that PHP does not know is ignored. */
    public static function timezoneFor(?object $user): string
    {
        $preference = $user !== null ? ($user->timezone ?? null) : null;

        if (is_string($preference) && isset(self::known()[$preference])) {
            return $preference;
        }

        $display = config('base-tenant.display_timezone');

        return is_string($display) && isset(self::known()[$display])
            ? $display
            : (string) config('app.timezone');
    }

    /** @return array<string, true> */
    private static function known(): array
    {
        return self::$known ??= array_fill_keys(timezone_identifiers_list(), true);
    }
}
