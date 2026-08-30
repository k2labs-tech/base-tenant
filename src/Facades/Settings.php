<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Settings\SettingsBag;
use Base\Tenant\Settings\SettingsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static SettingsBag for(Model $owner)
 * @method static SettingsBag current()
 * @method static SettingsBag|null user()
 *
 * @see SettingsManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
