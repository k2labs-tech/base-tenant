<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\Artisan;

class MigrationRunner
{
    /**
     * Run package migrations
     */
    public function runPackageMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * Seed default roles
     */
    public function seedDefaultRoles(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Base\\Tenant\\Database\\Seeders\\InitialLoadSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Create the platform administrator.
     *
     * Delegates to the seeder so there is one implementation, whether the user
     * arrives through the installer or through `migrate:fresh --seed`.
     */
    public function createAdminUser(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Base\Tenant\Database\Seeders\AdminUserSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Create test user if requested
     */
    public function createTestUser(bool $create): void
    {
        if (! $create) {
            return;
        }

        Artisan::call('db:seed', [
            '--class' => 'Base\\Tenant\\Database\\Seeders\\TestUserSeeder',
            '--force' => true,
        ]);
    }
}
