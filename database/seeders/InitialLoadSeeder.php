<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Database\Seeder;

/**
 * Initial Load Seeder - Creates default system and customer roles
 *
 * This seeder syncs all configured roles (both system and customer) to the database.
 * It is typically run during initial setup to populate default roles.
 */
class InitialLoadSeeder extends Seeder
{
    use HasExtensibleRoles;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Syncing default roles from configuration...');

        // Sync all roles from configuration
        static::syncRolesToDatabase();

        $this->command->info('Default roles created successfully!');
    }
}
