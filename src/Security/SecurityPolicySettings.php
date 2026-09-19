<?php

declare(strict_types=1);

namespace Base\Tenant\Security;

use Base\Tenant\Settings\SettingsSchema;

/**
 * The security rules a customer sets for their own account.
 *
 * These live in the typed settings store rather than in columns on `accounts`
 * because that is where per-account configuration belongs here, and because it
 * means adding a rule is a property on this class and nothing else.
 *
 * Every default is the permissive one. A rule that arrives switched on with an
 * upgrade would lock people out of an account nobody asked to change.
 */
class SecurityPolicySettings extends SettingsSchema
{
    public const IP_OFF = 'off';

    public const IP_WARN = 'warn';

    public const IP_ENFORCE = 'enforce';

    public static function group(): string
    {
        return 'security';
    }

    /** Every member of the account must have a second factor. */
    public bool $requireTwoFactor = false;

    /**
     * How long somebody has to set it up before being made to.
     *
     * Without a grace period, switching the rule on throws every member out of
     * the product at once, including the person who switched it on.
     */
    public int $twoFactorGraceHours = 72;

    /**
     * Email domains that may be invited into the account, lower-cased and
     * without the `@`. Empty means no restriction.
     *
     * @var array<int, string>
     */
    public array $allowedEmailDomains = [];

    /** `off`, `warn` or `enforce`. */
    public string $ipMode = self::IP_OFF;

    /**
     * Addresses and CIDR ranges allowed to reach the account.
     *
     * @var array<int, string>
     */
    public array $ipAllowlist = [];

    /** Minutes of inactivity before the session is ended. 0 leaves the framework default. */
    public int $sessionTimeoutMinutes = 0;

    public function enforcesIp(): bool
    {
        return $this->ipMode === self::IP_ENFORCE && $this->ipAllowlist !== [];
    }

    public function warnsOnIp(): bool
    {
        return $this->ipMode === self::IP_WARN && $this->ipAllowlist !== [];
    }
}
