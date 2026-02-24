<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\Artisan;

class MigrationRunner
{
    /**
     * Run package migrations
     */
    public function runPackageMigrations(bool $fresh = false): void
    {
        if ($fresh) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } else {
            Artisan::call('migrate', ['--force' => true]);
        }
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
