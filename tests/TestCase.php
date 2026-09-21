<?php

declare(strict_types=1);

namespace Base\Tenant\Tests;

use Base\Tenant\BaseTenantServiceProvider;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Services\PermissionRegistry;
use Flux\FluxServiceProvider;
use FluxPro\FluxProServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lab404\Impersonate\ImpersonateServiceProvider;
use Laravel\Cashier\CashierServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    use TenancyAssertions;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Base\\Tenant\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function tearDown(): void
    {
        Tenant::forget();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            PermissionServiceProvider::class,
            CashierServiceProvider::class,
            ImpersonateServiceProvider::class,
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            // Flux Pro is optional: the package uses only free Flux components.
            class_exists(FluxProServiceProvider::class) ? FluxProServiceProvider::class : null,
            BaseTenantServiceProvider::class,
        ]));
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', $this->connectionConfig());

        config()->set('base-tenant.subscription.enabled', false);
        config()->set('base-tenant.multi_team', true);
        config()->set('base-tenant.home_url', 'base-tenant.dashboard');
        config()->set('base-tenant.menu.cache.enabled', false);
        config()->set('auth.providers.users.model', User::class);
    }

    /**
     * SQLite in memory by default. Exporting `DB_CONNECTION=pgsql` (or
     * `mysql`) runs the same suite against a real server, which is the only
     * way to catch what SQLite forgives — column lengths, strict types and
     * anything else it does not enforce.
     */
    protected function connectionConfig(): array
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
                'foreign_key_constraints' => false,
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'base_tenant_test'),
            'username' => env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    /**
     * Write the configured permission catalogue and global roles, which most
     * tests need before they can assign anything.
     */
    protected function syncPermissions(): void
    {
        PermissionRegistry::sync();
    }

    protected function createAccount(array $attributes = []): Account
    {
        return Account::factory()->create($attributes);
    }

    /**
     * A user attached to an account, optionally holding a role inside it.
     */
    protected function createUser(?Account $account = null, ?string $role = null, array $attributes = []): User
    {
        $account ??= $this->createAccount();

        $user = User::factory()->create([
            'account_id' => $account->getKey(),
            ...$attributes,
        ]);

        $user->accounts()->syncWithoutDetaching([$account->getKey()]);

        if ($role) {
            Tenant::runFor($account, fn () => $user->assignRole($role));
        }

        return $user;
    }

    /**
     * Build a role owned by the account with an explicit permission list.
     *
     * @param  array<int, string>  $permissions
     */
    protected function createRole(Account $account, string $name, array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'display_name' => $name,
            'guard_name' => 'web',
            'account_id' => $account->getKey(),
            'is_system' => false,
        ]);

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    /**
     * Authenticate as a user of the given account, with that account in
     * context, which is what a real request would look like.
     */
    protected function actingAsTenant(User $user, ?Account $account = null): static
    {
        Tenant::set($account ?? $user->account);

        return $this->actingAs($user);
    }
}
