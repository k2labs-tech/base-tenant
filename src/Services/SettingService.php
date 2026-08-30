<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;

class SettingService
{
    public static function get(string $key, mixed $default = null, ?User $user = null, ?Account $account = null): mixed
    {
        if ($user) {
            $value = $user->getSetting($key);

            if ($value !== null) {
                return $value;
            }
        }

        if ($account) {
            $value = $account->getSetting($key);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    public static function resolve(string $key, mixed $default = null): mixed
    {
        return static::get($key, $default, auth()->user(), Tenant::current());
    }
}
