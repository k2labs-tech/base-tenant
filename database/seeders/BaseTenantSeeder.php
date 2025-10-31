<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BaseTenantSeeder extends Seeder
{
    use HasExtensibleRoles;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Syncing roles from configuration...');

        // Sync all roles from configuration
        static::syncRolesToDatabase();

        $this->command->info('Creating default admin user...');

        // Create admin user
        $adminUser = $this->getModelClass('user')::create([
            'is_admin' => true,
            'account_id' => null,
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'email_verified_at' => now(),
        ]);
        $adminUser->addRole('administrator');

        $this->command->info('Creating test customer...');

        // Create customer user
        $customerUser = $this->getModelClass('user')::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make('secret123'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        if ($customerUser) {
            $account = $customerUser->createPrimaryAccountAndSetRole("Customer's Company");

            $this->command->info('Creating additional test users...');

            // Create additional test users
            for ($i = 1; $i < 10; $i++) {
                $user = $this->getModelClass('user')::create([
                    'name' => 'Customer '.$i,
                    'email' => "customer{$i}@example.com",
                    'account_id' => (string) $account->id,
                    'password' => Hash::make('secret123'),
                    'email_verified_at' => now(),
                ]);
                $user->addRole('customer-finance');
                $user->accounts()->attach($account->id);
            }
        }

        $this->command->info('Base Tenant seeding completed!');
    }

    /**
     * Get model class from configuration.
     */
    protected function getModelClass(string $type): string
    {
        return config("base-tenant.models.{$type}");
    }
}
