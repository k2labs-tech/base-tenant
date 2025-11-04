<?php

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get User model from config
        $userModel = config('base-tenant.models.user');

        // Create test account
        $account = Account::create([
            'id' => Str::uuid(),
            'name' => 'Test Company',
            'email' => 'admin@test.com',
        ]);

        // Create test user
        $user = $userModel::create([
            'id' => Str::uuid(),
            'account_id' => $account->id,
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'timezone' => 'Europe/Madrid',
            'default_locale' => 'en',
        ]);

        // Assign admin role
        $adminRole = Role::where('key', 'customer-admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole);
        }

        $this->command->info('Test user created:');
        $this->command->info('Email: admin@test.com');
        $this->command->info('Password: password');
    }
}
