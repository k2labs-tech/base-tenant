<?php

declare(strict_types=1);
use Base\Tenant\Models\User;
use Base\Tenant\Services\SettingService;

if (! function_exists('tenant_setting')) {
    function tenant_setting(string $key, mixed $default = null): mixed
    {
        return SettingService::resolve($key, $default);
    }
}

if (! function_exists('tenant_user_model')) {
    /**
     * The user model this installation actually uses.
     *
     * The package must never name `App\Models\User`: that class does not
     * exist in a package's own test suite, and a host that swapped the model
     * would be ignored. Everything that looks a user up by hand goes through
     * here.
     *
     * @return class-string<User>
     */
    function tenant_user_model(): string
    {
        return config('base-tenant.models.user', User::class);
    }
}
