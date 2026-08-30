<?php

declare(strict_types=1);

namespace Base\Tenant\Transfer;

/**
 * One kind of import.
 *
 *     class GuestImport extends Import
 *     {
 *         public function columns(): array
 *         {
 *             return [
 *                 'name' => 'Name',
 *                 'email' => 'Email',
 *                 'phone' => 'Phone',
 *             ];
 *         }
 *
 *         public function rules(): array
 *         {
 *             return [
 *                 'name' => ['required', 'string', 'max:255'],
 *                 'email' => ['required', 'email'],
 *                 'phone' => ['nullable', 'string'],
 *             ];
 *         }
 *
 *         public function persist(array $row): void
 *         {
 *             Guest::updateOrCreate(['email' => $row['email']], $row);
 *         }
 *     }
 *
 * `persist()` runs inside the account the transfer belongs to, so a model
 * using `BelongsToAccount` is stamped correctly with nothing extra here.
 */
abstract class Import
{
    /**
     * Field name => the heading a user would recognise.
     *
     * The key is what `persist()` receives; the label is what the mapping
     * screen offers and what the sample file is headed with.
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * Validation for one row, keyed by field.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * Store one validated row.
     *
     * @param  array<string, mixed>  $row
     */
    abstract public function persist(array $row): void;

    /**
     * Fields that must be mapped before the import can start.
     *
     * Derived from the rules: anything `required` has to come from somewhere.
     * Override when a field is required in the database but computed here.
     *
     * @return list<string>
     */
    public function required(): array
    {
        $required = [];

        foreach ($this->rules() as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($rules as $rule) {
                if (is_string($rule) && ($rule === 'required' || str_starts_with($rule, 'required_'))) {
                    $required[] = $field;

                    break;
                }
            }
        }

        return $required;
    }

    /**
     * Rows per queued chunk. Small enough that a failure loses little work,
     * large enough that the queue is not the bottleneck.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Shown in the interface. A translation key.
     */
    public function label(): string
    {
        return static::class;
    }

    /**
     * Turn a raw cell into what `persist()` should receive.
     *
     * The default is a trim, which handles the single most common cause of
     * "this row failed validation and looks fine": a trailing space.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    public function prepare(array $row): array
    {
        return array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $row);
    }
}
