<?php

declare(strict_types=1);

namespace Base\Tenant\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * A typed group of settings.
 *
 * Declare public typed properties with defaults; the values are read from and
 * written to the key-value store using the property names, so a schema class
 * is both the contract and the default:
 *
 *     class BrandSettings extends SettingsSchema
 *     {
 *         public static function group(): string { return 'brand'; }
 *
 *         public string $tone = 'neutral';
 *         public bool $signOffWithName = true;
 *     }
 *
 *     $brand = Settings::for($account)->get(BrandSettings::class);
 *     $brand->tone = 'playful';
 *     $brand->save();
 */
abstract class SettingsSchema
{
    protected Model $owner;

    /**
     * The settings group these values are stored under.
     */
    abstract public static function group(): string;

    public static function for(Model $owner): static
    {
        $schema = new static;
        $schema->owner = $owner;

        return $schema->load();
    }

    public function load(): static
    {
        $stored = $this->owner->getSettingsByGroup(static::group());

        foreach ($this->properties() as $property) {
            if (! array_key_exists($property->getName(), $stored)) {
                continue;
            }

            $property->setValue($this, $this->cast($property, $stored[$property->getName()]));
        }

        return $this;
    }

    public function save(): static
    {
        foreach ($this->toArray() as $key => $value) {
            $this->owner->setSetting($key, $value, $this->typeOf($key), static::group());
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fill(array $values): static
    {
        foreach ($this->properties() as $property) {
            if (! array_key_exists($property->getName(), $values)) {
                continue;
            }

            $property->setValue($this, $this->cast($property, $values[$property->getName()]));
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->properties() as $property) {
            $values[$property->getName()] = $property->getValue($this);
        }

        return $values;
    }

    /**
     * Field metadata for rendering a form: the declared type decides the
     * control, so a schema is the only place a setting has to be described.
     *
     * @return array<int, array{name: string, type: string, value: mixed}>
     */
    public function fields(): array
    {
        return array_map(fn (ReflectionProperty $property): array => [
            'name' => $property->getName(),
            'type' => $this->typeOf($property->getName()),
            'value' => $property->getValue($this),
        ], $this->properties());
    }

    /**
     * Label for the group, taken from the translation files when there is one.
     */
    public static function label(): string
    {
        $key = 'base-tenant::settings.groups.'.static::group();
        $translated = __($key);

        return $translated === $key ? Str::headline(static::group()) : $translated;
    }

    public function owner(): Model
    {
        return $this->owner;
    }

    /** @return array<int, ReflectionProperty> */
    protected function properties(): array
    {
        return array_values(array_filter(
            (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => ! $property->isStatic()
        ));
    }

    protected function typeOf(string $property): string
    {
        $type = (new ReflectionProperty($this, $property))->getType();

        if (! $type instanceof ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'bool' => 'boolean',
            'int' => 'integer',
            'array' => 'json',
            default => 'string',
        };
    }

    protected function cast(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType || $value === null) {
            return $value;
        }

        return match ($type->getName()) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'array' => is_array($value) ? $value : (array) json_decode((string) $value, true),
            default => (string) $value,
        };
    }
}
