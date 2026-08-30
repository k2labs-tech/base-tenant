<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ConflictDetector
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Detect conflicting migration files
     */
    public function detectConflictingMigrations(): array
    {
        $migrationsPath = $this->basePath.'/database/migrations';

        if (! File::exists($migrationsPath)) {
            return [];
        }

        $conflicts = [];
        $patterns = [
            '*create_users_table.php',
            '*create_cache_table.php',
            '*create_jobs_table.php',
            '*two_factor*.php',
        ];

        foreach ($patterns as $pattern) {
            $files = File::glob($migrationsPath.'/'.$pattern);
            foreach ($files as $file) {
                $conflicts[] = str_replace($this->basePath.'/', '', $file);
            }
        }

        return $conflicts;
    }

    /**
     * Check if User model already extends BaseTenantUser
     */
    public function detectUserModelConflict(): bool
    {
        $userModelPath = $this->basePath.'/app/Models/User.php';

        if (! File::exists($userModelPath)) {
            return false;
        }

        $content = File::get($userModelPath);

        return str_contains($content, 'extends BaseTenantUser') ||
               str_contains($content, 'Base\Tenant\Models\User');
    }

    /**
     * Check if base-tenant is already installed
     */
    /**
     * Whether a previous installation exists that re-running would disturb.
     *
     * Configuration alone is not enough of a signal: a starter kit ships the
     * config file, the environment variables and a User model extending the
     * package on purpose, and a project created from one has installed
     * nothing yet. What settles it is whether the schema is in place.
     */
    public function isAlreadyInstalled(): bool
    {
        if (! $this->hasBeenMigrated()) {
            return false;
        }

        return File::exists($this->basePath.'/config/base-tenant.php')
            && $this->hasBaseTenantEnvVariables()
            && $this->detectUserModelConflict();
    }

    public function hasBeenMigrated(): bool
    {
        try {
            return Schema::hasTable('accounts');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if .env has BASE_TENANT variables
     */
    protected function hasBaseTenantEnvVariables(): bool
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            return false;
        }

        $content = File::get($envPath);

        return str_contains($content, 'BASE_TENANT_');
    }
}
