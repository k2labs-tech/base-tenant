<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Collects and applies the database connection during installation.
 *
 * Getting this wrong is expensive: the installer runs migrations and seeds, and
 * unpicking that from the wrong database is worse than being asked four
 * questions up front. So the connection is tested before anything is written,
 * and applied to the running process as well as to `.env`, so the migrations
 * that follow in the same command use it.
 */
class DatabaseConfigurator
{
    public const DRIVERS = ['sqlite', 'mysql', 'mariadb', 'pgsql'];

    protected string $lastError = '';

    public function __construct(
        protected string $basePath,
        protected EnvironmentManager $environment,
    ) {}

    /**
     * Whether the connection the application is already configured with works.
     */
    public function currentConnectionWorks(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return false;
        }
    }

    public function currentDriver(): string
    {
        return (string) config('database.default', 'sqlite');
    }

    /**
     * A description of the current connection, for the preview.
     */
    public function describe(?array $config = null): string
    {
        $config ??= $this->currentConfig();

        if ($config['driver'] === 'sqlite') {
            return 'sqlite: '.$this->relativePath($config['database']);
        }

        return sprintf(
            '%s: %s@%s:%s/%s',
            $config['driver'],
            $config['username'] ?: '(no user)',
            $config['host'],
            $config['port'],
            $config['database']
        );
    }

    /**
     * @return array<string, string>
     */
    public function currentConfig(): array
    {
        $driver = $this->currentDriver();

        return [
            'driver' => $driver,
            'host' => (string) config("database.connections.{$driver}.host", '127.0.0.1'),
            'port' => (string) config("database.connections.{$driver}.port", $this->defaultPort($driver)),
            'database' => (string) config("database.connections.{$driver}.database", ''),
            'username' => (string) config("database.connections.{$driver}.username", ''),
            'password' => (string) config("database.connections.{$driver}.password", ''),
        ];
    }

    public function defaultPort(string $driver): string
    {
        return match ($driver) {
            'pgsql' => '5432',
            default => '3306',
        };
    }

    public function defaultSqlitePath(): string
    {
        return $this->basePath.'/database/database.sqlite';
    }

    /**
     * Try to connect with the given settings without disturbing the
     * application's own connections.
     *
     * @param  array<string, string>  $config
     */
    public function test(array $config): bool
    {
        $name = 'base_tenant_probe';

        config(["database.connections.{$name}" => $this->connectionConfig($config)]);

        try {
            DB::connection($name)->getPdo();

            return true;
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();

            return false;
        } finally {
            DB::purge($name);
        }
    }

    /**
     * Write the settings to `.env` and make the running process use them, so
     * the migrations that follow do not go to the previous database.
     *
     * @param  array<string, string>  $config
     */
    public function apply(array $config): void
    {
        if ($config['driver'] === 'sqlite') {
            $this->ensureSqliteFileExists($config['database']);
        }

        $this->environment->setValues($this->environmentValues($config));

        $driver = $config['driver'];

        config([
            'database.default' => $driver,
            "database.connections.{$driver}" => $this->connectionConfig($config),
        ]);

        DB::purge($driver);
        DB::setDefaultConnection($driver);

        $this->rebindServicesHoldingTheOldConnection();
    }

    /**
     * Anything already resolved against the previous connection keeps using it.
     *
     * The database cache and session stores hold a Connection instance from the
     * moment they were first built, and spatie/laravel-permission keeps its own
     * cache repository. Switching `database.default` does not reach any of
     * them, so seeding would write its permission cache to the database we just
     * moved away from.
     */
    protected function rebindServicesHoldingTheOldConnection(): void
    {
        $app = app();

        if ($app->resolved('cache')) {
            $cache = $app->make('cache');

            foreach (array_keys((array) config('cache.stores', [])) as $store) {
                try {
                    $cache->purge($store);
                } catch (Throwable) {
                    // A store that cannot be purged was never built.
                }
            }
        }

        if ($app->resolved('session')) {
            $app->make('session')->forgetDrivers();
        }

        if ($app->resolved(PermissionRegistrar::class)) {
            $app->forgetInstance(PermissionRegistrar::class);
        }
    }

    public function ensureSqliteFileExists(string $path): void
    {
        if (File::exists($path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function relativePath(string $path): string
    {
        return str_starts_with($path, $this->basePath)
            ? ltrim(substr($path, strlen($this->basePath)), '/')
            : $path;
    }

    /**
     * The `.env` keys, with the ones the chosen driver does not use blanked so
     * a leftover value from a previous driver cannot confuse anyone reading it.
     *
     * @param  array<string, string>  $config
     * @return array<string, string>
     */
    protected function environmentValues(array $config): array
    {
        if ($config['driver'] === 'sqlite') {
            return [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $this->relativePath($config['database']),
                'DB_HOST' => '',
                'DB_PORT' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
            ];
        }

        return [
            'DB_CONNECTION' => $config['driver'],
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
        ];
    }

    /**
     * @param  array<string, string>  $config
     * @return array<string, mixed>
     */
    protected function connectionConfig(array $config): array
    {
        $base = config("database.connections.{$config['driver']}", []);

        if ($config['driver'] === 'sqlite') {
            return [...$base, 'driver' => 'sqlite', 'database' => $config['database']];
        }

        return [
            ...$base,
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
        ];
    }
}
