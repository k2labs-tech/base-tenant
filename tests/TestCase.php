<?php

declare(strict_types=1);

namespace Base\Tenant\Tests;

use Base\Tenant\BaseTenantServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Base\\Tenant\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            BaseTenantServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up package configuration for testing
        config()->set('base-tenant.subscription.enabled', false);
        config()->set('base-tenant.multi_team', false);
        config()->set('base-tenant.home_url', 'base-tenant.dashboard');

        // Run package migrations
        $migration = include __DIR__.'/../database/migrations/0001_01_00_000000_create_accounts_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/0001_01_01_000000_create_users_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2023_08_05_104819_create_roles_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2023_08_05_105633_create_role_user_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2023_08_06_213047_create_account_user_table.php';
        $migration->up();
    }
}
