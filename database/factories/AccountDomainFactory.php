<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Factories;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccountDomain>
 */
class AccountDomainFactory extends Factory
{
    protected $model = AccountDomain::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'hostname' => Str::lower(Str::random(8)).'.example.test',
            'verification_token' => 'base-tenant-verify='.Str::random(40),
            'status' => AccountDomain::STATUS_PENDING,
            'is_primary' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountDomain::STATUS_VERIFIED,
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);
    }

    public function primary(): static
    {
        return $this->verified()->state(fn (): array => ['is_primary' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountDomain::STATUS_FAILED,
            'last_checked_at' => now(),
        ]);
    }
}
