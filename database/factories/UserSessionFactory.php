<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Factories;

use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserSession>
 */
class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => UserSession::fingerprint(Str::random(40)),
            'ip_address' => '203.0.113.'.random_int(1, 254),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'device' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'macOS',
            'last_active_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
