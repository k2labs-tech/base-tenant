<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Facades\Language as LanguageFacade;
use Base\Tenant\Facades\Menu;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Language;
use Base\Tenant\Support\Module;
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
        $this->command->info('Syncing permissions and roles from configuration...');

        static::syncRolesToDatabase();

        $this->command->info('Syncing navigation menus...');

        Menu::sync();

        $this->seedLanguages();

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

            // Roles are assigned inside the account so they land on the right team.
            Tenant::runFor($account, function () use ($account): void {
                for ($i = 1; $i < 10; $i++) {
                    $user = $this->getModelClass('user')::create([
                        'name' => 'Customer '.$i,
                        'email' => "customer{$i}@example.com",
                        'account_id' => (string) $account->id,
                        'password' => Hash::make('secret123'),
                        'email_verified_at' => now(),
                    ]);

                    $user->accounts()->syncWithoutDetaching([$account->id]);
                    $user->addRole('customer-finance');
                }
            });
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

    /**
     * Put the configured languages in the table, once.
     *
     * `firstOrCreate` and not `updateOrCreate`: the table is the source of
     * truth once it exists, and re-seeding should not switch a language back
     * off because the config still says so.
     */
    protected function seedLanguages(): void
    {
        if (! Module::enabled(Module::LANGUAGES)) {
            return;
        }

        $this->command->info('Seeding languages...');

        foreach (config('base-tenant.languages.seed', []) as $language) {
            Language::firstOrCreate(['code' => $language['code']], $language);
        }

        LanguageFacade::flush();
    }
}
