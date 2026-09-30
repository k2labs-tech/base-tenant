<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

/**
 * The groups of routes the package registers and whether each one is on.
 *
 * Until 3.1 a single `routes.enabled` switch registered the screens to sign
 * in, the whole application behind them and the checkout at once, so a host
 * that only wanted the sign-in screens had to take the dashboard, the profile
 * and the user manager with them. Each group now has its own switch. A switch
 * left unset follows `routes.enabled`, which keeps an installation that never
 * heard of the groups exactly where it was.
 */
final class RouteGroup
{
    /**
     * Login, registration, password reset, email verification, second factor,
     * passkeys, magic links, social sign-in and invitation acceptance.
     */
    public const AUTH = 'auth';

    /**
     * The application screens: home, dashboard, profile, settings, users and
     * the rest of `routes/web.php`.
     */
    public const APP = 'app';

    /**
     * Checkout, billing portal and the checkout result pages. They also need
     * `subscription.enabled`.
     */
    public const SUBSCRIPTIONS = 'subscriptions';

    /**
     * Incoming webhooks (suppressions, LangSyncer). They carry no session and
     * are kept apart so an installation can drop the screens and keep them.
     */
    public const WEBHOOKS = 'webhooks';

    public static function enabled(string $group): bool
    {
        $value = config("base-tenant.routes.{$group}.enabled");

        // Null is the published default; an empty string is what an `.env`
        // line with nothing after the `=` gives. Both mean "not set".
        if ($value === null || $value === '') {
            $value = config('base-tenant.routes.enabled', true);
        }

        return (bool) $value;
    }

    /**
     * Whether the auth screens also answer to the names Laravel's own
     * middleware redirect to (`login`, `verification.notice`,
     * `password.confirm`...). Off by default: an application still running
     * Fortify or Breeze owns those names.
     */
    public static function registersLaravelNames(): bool
    {
        return self::enabled(self::AUTH)
            && (bool) config('base-tenant.routes.auth.laravel_names', false);
    }
}
