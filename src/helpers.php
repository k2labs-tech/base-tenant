<?php

declare(strict_types=1);
use Base\Tenant\Services\SettingService;

if (! function_exists('tenant_setting')) {
    function tenant_setting(string $key, mixed $default = null): mixed
    {
        return SettingService::resolve($key, $default);
    }
}
