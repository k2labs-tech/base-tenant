<?php

declare(strict_types=1);

namespace Base\Tenant\Support;

use Base\Tenant\Exceptions\ModuleDisabledException;

/**
 * The v2 modules and whether each one is switched on.
 *
 * Every module owns a top-level config section that carries its own `enabled`
 * key, so its switch sits next to its settings instead of in a separate
 * registry that has to be kept in step with them. This class is only the list
 * of names and the reading of that key -- there is no state to hold.
 */
final class Module
{
    public const METERING = 'metering';

    public const FILES = 'files';

    public const TRANSFER = 'transfer';

    public const CONNECTIONS = 'connections';

    public const WEBHOOKS = 'webhooks';

    public const LANGUAGES = 'languages';

    public const SOCIAL = 'social';

    public const SEQUENCES = 'sequences';

    public const ONBOARDING = 'onboarding';

    public const SUPPRESSIONS = 'suppressions';

    public const GDPR = 'gdpr';

    public const PRESALE = 'presale';

    /**
     * Every module the package ships, enabled or not.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::METERING,
            self::FILES,
            self::TRANSFER,
            self::CONNECTIONS,
            self::WEBHOOKS,
            self::LANGUAGES,
            self::SOCIAL,
            self::SEQUENCES,
            self::ONBOARDING,
            self::SUPPRESSIONS,
            self::GDPR,
            self::PRESALE,
        ];
    }

    public static function enabled(string $module): bool
    {
        return (bool) config("base-tenant.{$module}.enabled", false);
    }

    /**
     * @return list<string>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::all(), self::enabled(...)));
    }

    /**
     * Guard the entry points of a module -- a route, a facade, a command.
     *
     * A disabled module has to fail loudly and early: its tables may not even
     * exist, so letting a call through would surface as a SQL error a long way
     * from the switch that actually caused it.
     *
     * @throws ModuleDisabledException
     */
    public static function ensure(string $module): void
    {
        if (! self::enabled($module)) {
            throw ModuleDisabledException::for($module);
        }
    }
}
