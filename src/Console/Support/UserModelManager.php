<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;

class UserModelManager
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Replace User model with package version
     */
    public function replaceUserModel(): void
    {
        $targetPath = $this->basePath.'/app/Models/User.php';
        $stub = $this->getUserModelStub();

        File::put($targetPath, $stub);
    }

    /**
     * The User class the application resolves, which the package config and
     * `config/auth.php` both have to agree on.
     */
    public function applicationUserModel(): string
    {
        return 'App\\Models\\User';
    }

    /**
     * Get User model stub content
     */
    public function getUserModelStub(): string
    {
        $stubPath = __DIR__.'/../../../stubs/User.php.stub';

        if (! File::exists($stubPath)) {
            throw new \RuntimeException("User model stub not found at: {$stubPath}");
        }

        return File::get($stubPath);
    }
}
