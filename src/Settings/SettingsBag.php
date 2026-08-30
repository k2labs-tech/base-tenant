<?php

declare(strict_types=1);

namespace Base\Tenant\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * The settings of one owner: an account, a user, or anything else using the
 * HasSettings trait.
 */
class SettingsBag
{
    public function __construct(protected Model $owner) {}

    /**
     * @template T of SettingsSchema
     *
     * @param  class-string<T>  $schema
     * @return T
     */
    public function get(string $schema): SettingsSchema
    {
        return $schema::for($this->owner);
    }

    /**
     * @param  class-string<SettingsSchema>  $schema
     * @param  array<string, mixed>  $values
     */
    public function put(string $schema, array $values): SettingsSchema
    {
        return $schema::for($this->owner)->fill($values)->save();
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->owner->getSetting($key, $default);
    }

    public function setRaw(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $this->owner->setSetting($key, $value, $type, $group);
    }

    public function forget(string $key): bool
    {
        return $this->owner->removeSetting($key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->owner->getAllSettings();
    }
}
