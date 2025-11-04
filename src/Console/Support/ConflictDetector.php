<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;

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
    public function isAlreadyInstalled(): bool
    {
        $configExists = File::exists($this->basePath.'/config/base-tenant.php');
        $envHasVars = $this->hasBaseTenantEnvVariables();
        $userModelExtends = $this->detectUserModelConflict();

        return $configExists && $envHasVars && $userModelExtends;
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
