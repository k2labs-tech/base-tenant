<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * The credentials the installer reports, kept here so there is one place
     * that decides what the test user is.
     */
    public const NAME = 'Test Admin';

    public const EMAIL = 'test@example.com';

    public const PASSWORD = 'password';

    public const ACCOUNT = 'Test Company';

    public const ROLE = 'customer-admin';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userModel = config('base-tenant.models.user');

        if ($userModel::where('email', self::EMAIL)->exists()) {
            $this->command?->info('Test user '.self::EMAIL.' already exists, leaving it alone.');

            return;
        }

        $user = $userModel::create([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'timezone' => 'Europe/Madrid',
        ]);

        $user->createPrimaryAccountAndSetRole(self::ACCOUNT, self::ROLE);

        $this->command?->info('Test user created:');
        $this->command?->info('Email: '.self::EMAIL);
        $this->command?->info('Password: '.self::PASSWORD);
    }
}
