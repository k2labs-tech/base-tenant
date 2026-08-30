<?php

declare(strict_types=1);

namespace Base\Tenant\Settings;

use Base\Tenant\Exceptions\NoAccountException;
use Base\Tenant\Facades\Tenant;
use Illuminate\Database\Eloquent\Model;

class SettingsManager
{
    /** @var array<int, class-string<SettingsSchema>> */
    protected array $schemas = [];

    /**
     * Declare a schema so it appears in the settings editor. Called from a
     * service provider's boot, the same way menus are registered.
     *
     * @param  class-string<SettingsSchema>  ...$schemas
     */
    public function register(string ...$schemas): void
    {
        foreach ($schemas as $schema) {
            if (! in_array($schema, $this->schemas, true)) {
                $this->schemas[] = $schema;
            }
        }
    }

    /**
     * Schemas registered in code plus those listed in configuration.
     *
     * @return array<int, class-string<SettingsSchema>>
     */
    public function schemas(): array
    {
        return array_values(array_unique([
            ...config('base-tenant.settings.schemas', []),
            ...$this->schemas,
        ]));
    }

    public function for(Model $owner): SettingsBag
    {
        return new SettingsBag($owner);
    }

    /**
     * Settings of the account in context.
     */
    public function current(): SettingsBag
    {
        $account = Tenant::current();

        if (! $account) {
            throw NoAccountException::noActiveAccount();
        }

        return new SettingsBag($account);
    }

    public function user(): ?SettingsBag
    {
        $user = auth()->user();

        return $user ? new SettingsBag($user) : null;
    }
}
