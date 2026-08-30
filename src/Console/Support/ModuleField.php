<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use InvalidArgumentException;

/**
 * One field of a generated module, parsed from `--fields`.
 *
 *     --fields="name:string,price:decimal:nullable,active:boolean"
 */
final class ModuleField
{
    /** Type => [migration method, cast, validation rule, Flux input type]. */
    public const TYPES = [
        'string' => ['string', null, 'string|max:255', 'text'],
        'text' => ['text', null, 'string', 'textarea'],
        'integer' => ['integer', 'integer', 'integer', 'number'],
        'decimal' => ['decimal', 'decimal:2', 'numeric', 'number'],
        'boolean' => ['boolean', 'boolean', 'boolean', 'checkbox'],
        'date' => ['date', 'date', 'date', 'date'],
        'datetime' => ['dateTime', 'datetime', 'date', 'datetime-local'],
        'uuid' => ['uuid', null, 'uuid', 'text'],
        'json' => ['json', 'array', 'array', 'textarea'],
    ];

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = false,
    ) {
        if (! array_key_exists($type, self::TYPES)) {
            throw new InvalidArgumentException(sprintf(
                'Field `%s` has an unknown type `%s`. Known types: %s.',
                $name,
                $type,
                implode(', ', array_keys(self::TYPES)),
            ));
        }
    }

    /**
     * @return list<self>
     */
    public static function parse(string $definition): array
    {
        $fields = [];

        foreach (array_filter(array_map('trim', explode(',', $definition))) as $chunk) {
            $parts = explode(':', $chunk);

            $name = trim($parts[0]);

            if ($name === '') {
                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException(
                    "`{$name}` is not a usable column name: lowercase letters, digits and underscores."
                );
            }

            $fields[] = new self(
                name: $name,
                type: trim($parts[1] ?? 'string'),
                nullable: in_array('nullable', array_map('trim', array_slice($parts, 2)), true),
            );
        }

        return $fields;
    }

    public function migrationLine(): string
    {
        [$method] = self::TYPES[$this->type];

        $line = "            \$table->{$method}('{$this->name}')";

        if ($this->type === 'decimal') {
            $line = "            \$table->decimal('{$this->name}', 12, 2)";
        }

        if ($this->nullable) {
            $line .= '->nullable()';
        }

        // A boolean with no default is null on insert, and `null` is neither
        // true nor false in every place the value is read.
        if ($this->type === 'boolean' && ! $this->nullable) {
            $line .= '->default(false)';
        }

        return $line.';';
    }

    public function cast(): ?string
    {
        [, $cast] = self::TYPES[$this->type];

        return $cast === null ? null : "            '{$this->name}' => '{$cast}',";
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        [, , $rule] = self::TYPES[$this->type];

        return [$this->nullable ? 'nullable' : 'required', ...explode('|', $rule)];
    }

    public function rulesLine(): string
    {
        $rules = implode("', '", $this->rules());

        return "            '{$this->name}' => ['{$rules}'],";
    }

    public function inputType(): string
    {
        return self::TYPES[$this->type][3];
    }

    /**
     * The default the Livewire property starts at.
     *
     * A typed property with no default is uninitialised, and Livewire cannot
     * hydrate one: the form would fail before it rendered.
     */
    public function propertyLine(): string
    {
        return match ($this->type) {
            'boolean' => "    public bool \${$this->name} = false;",
            'integer' => "    public ?int \${$this->name} = null;",
            'decimal' => "    public ?string \${$this->name} = null;",
            'json' => "    public array \${$this->name} = [];",
            default => "    public ?string \${$this->name} = null;",
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->name));
    }

    /**
     * A value the factory can produce without guessing at semantics.
     */
    public function factoryLine(): string
    {
        $value = match ($this->type) {
            'text' => 'fake()->paragraph()',
            'integer' => 'fake()->numberBetween(1, 100)',
            'decimal' => 'fake()->randomFloat(2, 1, 1000)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime' => 'fake()->dateTime()',
            'uuid' => 'fake()->uuid()',
            'json' => '[]',
            default => 'fake()->words(3, true)',
        };

        return "            '{$this->name}' => {$value},";
    }

    /**
     * A value a test can set on the form and expect to pass validation.
     */
    public function testValue(): string
    {
        return match ($this->type) {
            'boolean' => 'true',
            'integer' => '42',
            'decimal' => "'19.99'",
            'date' => 'now()->addDay()->toDateString()',
            'datetime' => "now()->addDay()->format('Y-m-d\\TH:i')",
            'uuid' => '(string) \\Illuminate\\Support\\Str::uuid()',
            'json' => '[]',
            default => "'Valor de prueba'",
        };
    }
}
