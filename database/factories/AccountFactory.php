<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Factories;

use Base\Tenant\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Account>
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'active' => true,
            'address' => fake()->streetAddress(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'vat' => fake()->optional()->numerify('VAT#########'),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'user_id' => null,
        ];
    }
}
