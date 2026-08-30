<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Facades\Menu;
use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Database\Seeder;

/**
 * Writes the permission catalogue, the global roles and the product menus
 * declared in configuration. Run it on install and after any change to them.
 */
class InitialLoadSeeder extends Seeder
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

        $this->command->info('Default permissions, roles and menus created successfully!');
    }
}
