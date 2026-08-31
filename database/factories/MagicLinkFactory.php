<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Factories;

use Base\Tenant\Models\MagicLink;
use Base\Tenant\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagicLink>
 */
class MagicLinkFactory extends Factory
{
    protected $model = MagicLink::class;

    public function definition(): array
    {
        return [
            'email' => Str::lower(Str::random(8)).'@example.test',
            'user_id' => User::factory(),
            'token' => MagicLink::fingerprint(Str::random(40)),
            'ip_address' => '203.0.113.10',
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
