<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Models\Setting;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSettings
{
    public function settings(): MorphMany
    {
        return $this->morphMany(Setting::class, 'settingable');
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->casted_value;
    }

    public function setSetting(string $key, mixed $value, string $type = 'string', string $group = 'general'): Setting
    {
        return $this->settings()->updateOrCreate(
            ['key' => $key],
            [
                'value' => Setting::serializeValue($value, $type),
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    public function removeSetting(string $key): bool
    {
        return $this->settings()->where('key', $key)->delete() > 0;
    }

    public function getSettingsByGroup(string $group): array
    {
        return $this->settings()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->casted_value])
            ->toArray();
    }

    public function getAllSettings(): array
    {
        return $this->settings()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->casted_value])
            ->toArray();
    }

    public function setManySettings(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $config) {
            $value = is_array($config) ? ($config['value'] ?? null) : $config;
            $type = is_array($config) ? ($config['type'] ?? 'string') : 'string';

            $this->setSetting($key, $value, $type, $group);
        }
    }
}
