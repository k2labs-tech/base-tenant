# Install Command Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create an interactive `php artisan base-tenant:install` command that automatically integrates the package into fresh Laravel installations, eliminating 12 manual setup steps.

**Architecture:** Command orchestrates 3-phase process (collect options → preview → execute) using 4 support classes for conflict detection, User model management, environment configuration, and migration execution. All work happens in the base-tenant package at `/Users/nicolascantelipenic/Sites/base-tenant`.

**Tech Stack:** Laravel 12 Console Commands, Symfony Console Components, PHP 8.4

---

## Task 1: Create ConflictDetector Class

**Goal:** Detect conflicting migrations and existing installations

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/ConflictDetector.php`

**Step 1: Create ConflictDetector class**

Create file with complete implementation:

```php
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
```

**Step 2: Verify file was created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/ConflictDetector.php
```

Expected: File exists with ~90 lines

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Support/ConflictDetector.php
git commit -m "feat: add ConflictDetector for install command

Detects conflicting migrations, User model status, and existing
installations to provide safe installation experience.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Create UserModelManager Class

**Goal:** Manage User model replacement with stub

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/UserModelManager.php`
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/stubs/User.php.stub`

**Step 1: Create User.php.stub template**

```php
<?php

namespace App\Models;

use Base\Tenant\Models\User as BaseTenantUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends BaseTenantUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))
            ->implode('');
    }
}
```

**Step 2: Create UserModelManager class**

```php
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
```

**Step 3: Verify files created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/stubs/User.php.stub
ls -la /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/UserModelManager.php
```

Expected: Both files exist

**Step 4: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Support/UserModelManager.php stubs/User.php.stub
git commit -m "feat: add UserModelManager and User stub

Manages User model replacement with package version that extends
BaseTenantUser. Includes stub template with initials() helper.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Create EnvironmentManager Class

**Goal:** Manage .env variable addition

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/EnvironmentManager.php`

**Step 1: Create EnvironmentManager class**

```php
<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;

class EnvironmentManager
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Add BASE_TENANT variables to .env
     */
    public function addBaseTenantVariables(bool $multiTeam, bool $subscriptions): void
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $content = File::get($envPath);

        // Don't add if already exists
        if ($this->hasBaseTenantVariables()) {
            return;
        }

        $variables = $this->getVariablesBlock($multiTeam, $subscriptions);

        // Append to end of file
        $content .= "\n".$variables;

        File::put($envPath, $content);
    }

    /**
     * Check if .env already has BASE_TENANT variables
     */
    public function hasBaseTenantVariables(): bool
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            return false;
        }

        $content = File::get($envPath);

        return str_contains($content, 'BASE_TENANT_');
    }

    /**
     * Get environment variables block
     */
    protected function getVariablesBlock(bool $multiTeam, bool $subscriptions): string
    {
        $multiTeamValue = $multiTeam ? 'true' : 'false';
        $subscriptionsValue = $subscriptions ? 'true' : 'false';

        return <<<ENV

# Base Tenant Package Configuration
BASE_TENANT_MULTI_TEAM={$multiTeamValue}
BASE_TENANT_HOME_URL=base-tenant.dashboard
BASE_TENANT_SUBSCRIPTION_ENABLED={$subscriptionsValue}
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT=
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE=
BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14

# Stripe Configuration (when subscriptions enabled)
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
ENV;
    }
}
```

**Step 2: Verify file created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/EnvironmentManager.php
```

Expected: File exists with ~80 lines

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Support/EnvironmentManager.php
git commit -m "feat: add EnvironmentManager for .env configuration

Manages addition of BASE_TENANT_* environment variables based on
user choices (multi-team, subscriptions). Includes Stripe placeholders.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Create MigrationRunner Class

**Goal:** Execute migrations and seeders

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/MigrationRunner.php`

**Step 1: Create MigrationRunner class**

```php
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
```

**Step 2: Verify file created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Support/MigrationRunner.php
```

Expected: File exists with ~45 lines

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Support/MigrationRunner.php
git commit -m "feat: add MigrationRunner for database operations

Handles running package migrations, seeding default roles, and
creating test users. Uses Artisan facade for clean execution.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Create TestUserSeeder in Package

**Goal:** Move test user creation to package

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/database/seeders/TestUserSeeder.php`

**Step 1: Create TestUserSeeder**

```php
<?php

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get User model from config
        $userModel = config('base-tenant.models.user');

        // Create test account
        $account = Account::create([
            'id' => Str::uuid(),
            'name' => 'Test Company',
            'email' => 'admin@test.com',
        ]);

        // Create test user
        $user = $userModel::create([
            'id' => Str::uuid(),
            'account_id' => $account->id,
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'timezone' => 'Europe/Madrid',
            'default_locale' => 'en',
        ]);

        // Assign admin role
        $adminRole = Role::where('key', 'customer-admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole);
        }

        $this->command->info('Test user created:');
        $this->command->info('Email: admin@test.com');
        $this->command->info('Password: password');
    }
}
```

**Step 2: Verify file created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/database/seeders/TestUserSeeder.php
```

Expected: File exists

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add database/seeders/TestUserSeeder.php
git commit -m "feat: add TestUserSeeder to package

Creates test account and admin user for development/testing.
Uses configured User model from base-tenant config.

Credentials: admin@test.com / password

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Create InstallCommand (Part 1 - Structure & Phase 1)

**Goal:** Create command structure and interactive question phase

**Files:**
- Create: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php`

**Step 1: Create InstallCommand with Phase 1**

Create file with imports and Phase 1 implementation:

```php
<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Support\ConflictDetector;
use Base\Tenant\Console\Support\EnvironmentManager;
use Base\Tenant\Console\Support\MigrationRunner;
use Base\Tenant\Console\Support\UserModelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'base-tenant:install
                            {--no-interaction : Run without any interaction}';

    protected $description = 'Install and configure the Base Tenant package';

    protected ConflictDetector $conflictDetector;
    protected UserModelManager $userModelManager;
    protected EnvironmentManager $environmentManager;
    protected MigrationRunner $migrationRunner;

    // Installation options
    protected bool $multiTeam = false;
    protected bool $subscriptions = false;
    protected bool $createTestUser = true;
    protected array $conflictingMigrations = [];
    protected bool $userModelConflict = false;
    protected bool $alreadyInstalled = false;

    public function handle(): int
    {
        $basePath = base_path();

        $this->conflictDetector = new ConflictDetector($basePath);
        $this->userModelManager = new UserModelManager($basePath);
        $this->environmentManager = new EnvironmentManager($basePath);
        $this->migrationRunner = new MigrationRunner();

        $this->newLine();
        $this->components->info('Base Tenant Package Installation');
        $this->newLine();

        // Phase 1: Collect options and analyze
        if (! $this->collectOptionsAndAnalyze()) {
            return self::FAILURE;
        }

        // Phase 2: Show preview and get confirmation
        if (! $this->showPreviewAndConfirm()) {
            $this->components->info('Installation cancelled.');
            return self::SUCCESS;
        }

        // Phase 3: Execute installation
        return $this->executeInstallation();
    }

    /**
     * Phase 1: Collect options and analyze project
     */
    protected function collectOptionsAndAnalyze(): bool
    {
        // Check if already installed
        $this->alreadyInstalled = $this->conflictDetector->isAlreadyInstalled();

        if ($this->alreadyInstalled) {
            $this->components->warn('Base Tenant appears to be already installed.');

            if ($this->option('no-interaction')) {
                $this->components->error('Cannot proceed with --no-interaction on existing installation.');
                return false;
            }

            if (! $this->components->confirm('Re-install? This will overwrite existing configuration.', false)) {
                return false;
            }
        }

        // Ask questions (skip if no-interaction)
        if (! $this->option('no-interaction')) {
            $this->askInstallationQuestions();
        }

        // Detect conflicts
        $this->conflictingMigrations = $this->conflictDetector->detectConflictingMigrations();
        $this->userModelConflict = $this->conflictDetector->detectUserModelConflict();

        return true;
    }

    /**
     * Ask user installation questions
     */
    protected function askInstallationQuestions(): void
    {
        $this->components->info('Configuration Options');
        $this->newLine();

        // Multi-Team question
        $this->multiTeam = $this->components->confirm(
            '🏢 Enable Multi-Team Mode? (Users can belong to multiple accounts)',
            false
        );

        // Subscriptions question
        $this->subscriptions = $this->components->confirm(
            '💳 Enable Stripe Subscriptions? (Requires Stripe account)',
            false
        );

        // Test user question
        $this->createTestUser = $this->components->confirm(
            '👤 Create Test User? (admin@test.com / password)',
            true
        );

        $this->newLine();
    }

    /**
     * Phase 2: Show preview and get confirmation (placeholder)
     */
    protected function showPreviewAndConfirm(): bool
    {
        // Will implement in next task
        return true;
    }

    /**
     * Phase 3: Execute installation (placeholder)
     */
    protected function executeInstallation(): int
    {
        // Will implement in next task
        return self::SUCCESS;
    }
}
```

**Step 2: Verify file created**

Run:
```bash
ls -la /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php
```

Expected: File exists with ~130 lines

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Commands/InstallCommand.php
git commit -m "feat: add InstallCommand Phase 1 (options collection)

Implements interactive question flow for multi-team, subscriptions,
and test user options. Detects existing installations and conflicts.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: Complete InstallCommand (Part 2 - Phase 2 Preview)

**Goal:** Implement preview/dry-run phase

**Files:**
- Modify: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php`

**Step 1: Add Phase 2 implementation**

Replace the `showPreviewAndConfirm()` method:

```php
    /**
     * Phase 2: Show preview and get confirmation
     */
    protected function showPreviewAndConfirm(): bool
    {
        $this->components->twoColumnDetail('╔══════════════════════════════════════════════════════════════╗', '');
        $this->components->twoColumnDetail('║  Base Tenant Installation Plan', '║');
        $this->components->twoColumnDetail('╚══════════════════════════════════════════════════════════════╝', '');
        $this->newLine();

        // Configuration summary
        $this->components->info('Configuration:');
        $this->components->twoColumnDetail(
            '  Multi-Team',
            $this->multiTeam ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>'
        );
        $this->components->twoColumnDetail(
            '  Subscriptions',
            $this->subscriptions ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>'
        );
        $this->components->twoColumnDetail(
            '  Test User',
            $this->createTestUser ? '<fg=green>Yes (admin@test.com)</>' : '<fg=yellow>No</>'
        );
        $this->newLine();

        // Files to be modified
        $this->components->info('Files to be modified:');

        foreach ($this->conflictingMigrations as $migration) {
            $this->components->twoColumnDetail("  <fg=yellow>DELETE</>", $migration);
        }

        if (! $this->userModelConflict) {
            $this->components->twoColumnDetail('  <fg=yellow>REPLACE</>', 'app/Models/User.php');
        }

        $this->components->twoColumnDetail('  <fg=green>CREATE</>', 'config/base-tenant.php');
        $this->components->twoColumnDetail('  <fg=blue>UPDATE</>', '.env (BASE_TENANT_* variables)');
        $this->newLine();

        // Database operations
        $this->components->info('Database operations:');
        $this->components->twoColumnDetail('  <fg=green>✓</>', 'Run 17 package migrations');
        $this->components->twoColumnDetail('  <fg=green>✓</>', 'Seed 7 default roles');
        if ($this->createTestUser) {
            $this->components->twoColumnDetail('  <fg=green>✓</>', 'Create test account and user');
        }
        $this->newLine();

        $this->line('───────────────────────────────────────────────────────────────');
        $this->newLine();

        // Get confirmation
        if ($this->option('no-interaction')) {
            $this->components->info('Running in non-interactive mode. Proceeding with installation...');
            return true;
        }

        return $this->components->confirm('⚠️  This will modify your application. Continue?', true);
    }
```

**Step 2: Verify change**

Run:
```bash
grep -A 5 "Phase 2: Show preview" /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php
```

Expected: See new implementation

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Commands/InstallCommand.php
git commit -m "feat: add InstallCommand Phase 2 (preview/dry-run)

Shows comprehensive preview of all changes before execution:
- Configuration summary
- Files to be modified
- Database operations
- Confirmation prompt

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 8: Complete InstallCommand (Part 3 - Phase 3 Execution)

**Goal:** Implement execution phase with progress

**Files:**
- Modify: `/Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php`

**Step 1: Add Phase 3 implementation**

Replace the `executeInstallation()` method:

```php
    /**
     * Phase 3: Execute installation
     */
    protected function executeInstallation(): int
    {
        $this->components->twoColumnDetail('╔══════════════════════════════════════════════════════════════╗', '');
        $this->components->twoColumnDetail('║  Installing Base Tenant...', '║');
        $this->components->twoColumnDetail('╚══════════════════════════════════════════════════════════════╝', '');
        $this->newLine();

        $step = 1;
        $totalSteps = 7;

        try {
            // Step 1: Clean migrations
            $this->components->task("[{$step}/{$totalSteps}] Cleaning conflicting migrations", function () {
                return $this->cleanConflictingMigrations();
            });
            $step++;

            // Step 2: Update User model
            $this->components->task("[{$step}/{$totalSteps}] Updating User model", function () {
                return $this->updateUserModel();
            });
            $step++;

            // Step 3: Publish config
            $this->components->task("[{$step}/{$totalSteps}] Publishing configuration", function () {
                return $this->publishConfiguration();
            });
            $step++;

            // Step 4: Update environment
            $this->components->task("[{$step}/{$totalSteps}] Updating environment variables", function () {
                return $this->updateEnvironment();
            });
            $step++;

            // Step 5: Run migrations
            $this->components->task("[{$step}/{$totalSteps}] Running migrations", function () {
                return $this->runMigrations();
            });
            $step++;

            // Step 6: Seed roles
            $this->components->task("[{$step}/{$totalSteps}] Seeding default roles", function () {
                return $this->seedRoles();
            });
            $step++;

            // Step 7: Create test data
            $this->components->task("[{$step}/{$totalSteps}] Creating test data", function () {
                return $this->createTestData();
            });

            // Success summary
            $this->showSuccessSummary();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->components->error('Installation failed: '.$e->getMessage());
            $this->components->warn('Please check the error above and try again.');
            return self::FAILURE;
        }
    }

    /**
     * Clean conflicting migrations
     */
    protected function cleanConflictingMigrations(): bool
    {
        foreach ($this->conflictingMigrations as $migration) {
            $fullPath = base_path($migration);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }

        return true;
    }

    /**
     * Update User model
     */
    protected function updateUserModel(): bool
    {
        if (! $this->userModelConflict) {
            $this->userModelManager->replaceUserModel();
        }

        return true;
    }

    /**
     * Publish configuration
     */
    protected function publishConfiguration(): bool
    {
        // Publish config
        $this->callSilent('vendor:publish', [
            '--tag' => 'base-tenant-config',
            '--force' => true,
        ]);

        // Update config to use App\Models\User
        $configPath = config_path('base-tenant.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = str_replace(
                "'user' => env('BASE_TENANT_USER_MODEL', \\Base\\Tenant\\Models\\User::class)",
                "'user' => env('BASE_TENANT_USER_MODEL', \\App\\Models\\User::class)",
                $content
            );
            File::put($configPath, $content);
        }

        return true;
    }

    /**
     * Update environment variables
     */
    protected function updateEnvironment(): bool
    {
        $this->environmentManager->addBaseTenantVariables(
            $this->multiTeam,
            $this->subscriptions
        );

        return true;
    }

    /**
     * Run migrations
     */
    protected function runMigrations(): bool
    {
        $this->migrationRunner->runPackageMigrations();

        return true;
    }

    /**
     * Seed default roles
     */
    protected function seedRoles(): bool
    {
        $this->migrationRunner->seedDefaultRoles();

        return true;
    }

    /**
     * Create test data
     */
    protected function createTestData(): bool
    {
        $this->migrationRunner->createTestUser($this->createTestUser);

        return true;
    }

    /**
     * Show success summary
     */
    protected function showSuccessSummary(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('╔══════════════════════════════════════════════════════════════╗', '');
        $this->components->twoColumnDetail('║  Installation Complete! 🎉', '║');
        $this->components->twoColumnDetail('╚══════════════════════════════════════════════════════════════╝', '');
        $this->newLine();

        if ($this->createTestUser) {
            $this->components->info('Test Credentials:');
            $this->components->twoColumnDetail('  Email', 'admin@test.com');
            $this->components->twoColumnDetail('  Password', 'password');
            $this->newLine();
        }

        $this->components->info('Next Steps:');
        $this->line('  1. Start server: <fg=green>php artisan serve</>');
        $this->line('  2. Visit: <fg=green>http://127.0.0.1:8000/login</>');
        $this->line('  3. Review docs: <fg=green>vendor/base/tenant/README.md</>');
        $this->newLine();

        if ($this->subscriptions) {
            $this->components->warn('Don\'t forget to add your Stripe keys to .env!');
            $this->newLine();
        }
    }
```

**Step 2: Verify change**

Run:
```bash
grep -c "executeInstallation\|cleanConflictingMigrations\|showSuccessSummary" /Users/nicolascantelipenic/Sites/base-tenant/src/Console/Commands/InstallCommand.php
```

Expected: Shows methods exist

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/Console/Commands/InstallCommand.php
git commit -m "feat: add InstallCommand Phase 3 (execution)

Implements complete installation execution with:
- Progress tracking (7 steps)
- Clean error handling
- Success summary with credentials
- Next steps guidance

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 9: Register InstallCommand in ServiceProvider

**Goal:** Make command available via artisan

**Files:**
- Modify: `/Users/nicolascantelipenic/Sites/base-tenant/src/BaseTenantServiceProvider.php`

**Step 1: Add InstallCommand import**

Add to imports at top of file:

```php
use Base\Tenant\Console\Commands\InstallCommand;
```

**Step 2: Register command**

In the `registerCommands()` method, add `InstallCommand::class` to the array:

```php
protected function registerCommands(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            SyncRolesCommand::class,
            InstallCommand::class, // Add this line
        ]);
    }
}
```

**Step 3: Verify changes**

Run:
```bash
grep -A 5 "registerCommands" /Users/nicolascantelipenic/Sites/base-tenant/src/BaseTenantServiceProvider.php
```

Expected: See InstallCommand registered

**Step 4: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add src/BaseTenantServiceProvider.php
git commit -m "feat: register InstallCommand in service provider

Makes base-tenant:install command available via artisan.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 10: Update Package README

**Goal:** Document new installation flow

**Files:**
- Modify: `/Users/nicolascantelipenic/Sites/base-tenant/README.md`

**Step 1: Update Installation section**

Replace the Installation section (lines 24-64) with:

```markdown
## Installation

### Automatic Installation (Recommended)

1. Add the package to your project:

```bash
composer require base/tenant
```

2. Run the interactive installer:

```bash
php artisan base-tenant:install
```

The installer will:
- Ask configuration questions (multi-team, subscriptions, test user)
- Show preview of all changes
- Clean conflicting Laravel migrations
- Update User model to extend base-tenant
- Publish and configure settings
- Run migrations and seed roles
- Create test user (optional)

**Non-Interactive Mode (CI/CD):**

```bash
php artisan base-tenant:install --no-interaction
```

Uses default settings: single-team, no subscriptions, with test user.

### Manual Installation

If you prefer manual setup, see [Manual Integration Guide](docs/BASE_TENANT_INTEGRATION.md).

## Quick Start

After installation:

```bash
# Start the development server
php artisan serve

# Visit http://127.0.0.1:8000/login
# Login with: admin@test.com / password
```
```

**Step 2: Verify change**

Run:
```bash
head -80 /Users/nicolascantelipenic/Sites/base-tenant/README.md | tail -40
```

Expected: See new installation instructions

**Step 3: Commit**

```bash
cd /Users/nicolascantelipenic/Sites/base-tenant
git add README.md
git commit -m "docs: update README with automatic installation

Documents new php artisan base-tenant:install command as the
primary installation method. Includes non-interactive mode for CI/CD.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 11: Test Installation in localization-hub

**Goal:** Verify the command works in actual project

**Files:** None (testing only)

**Step 1: Clear autoload cache**

Run from localization-hub:
```bash
cd /Users/nicolascantelipenic/Sites/localization-hub
composer dump-autoload
```

Expected: Autoload files regenerated

**Step 2: Verify command is available**

Run:
```bash
php artisan list | grep base-tenant
```

Expected: See `base-tenant:install` in the list

**Step 3: Run installation command**

Run:
```bash
php artisan base-tenant:install
```

Expected:
- Shows welcome message
- Asks 3 questions (multi-team, subscriptions, test-user)
- Shows preview with all changes
- Asks for confirmation
- Executes 7 steps successfully
- Shows success message with credentials

**Step 4: Verify results**

Run these verification commands:

```bash
# Check User model was updated
grep "extends BaseTenantUser" app/Models/User.php

# Check config was published
ls -la config/base-tenant.php

# Check .env was updated
grep "BASE_TENANT_" .env

# Check migrations ran
php artisan migrate:status | grep "Base Tenant"

# Check roles were seeded
php artisan tinker --execute="echo 'Roles: ' . Base\Tenant\Models\Role::count();"

# Check test user exists (if created)
php artisan tinker --execute="echo 'User: ' . App\Models\User::where('email', 'admin@test.com')->first()->name;"
```

Expected: All verifications pass

**Step 5: Test login**

Run:
```bash
php artisan serve
```

Visit http://127.0.0.1:8000/login and login with admin@test.com / password

Expected: Login successful, dashboard loads

---

## Final Verification Checklist

After all tasks complete, verify:

- [ ] All 4 support classes created (ConflictDetector, UserModelManager, EnvironmentManager, MigrationRunner)
- [ ] User.php.stub created
- [ ] TestUserSeeder in package
- [ ] InstallCommand complete with 3 phases
- [ ] Command registered in ServiceProvider
- [ ] README updated with new flow
- [ ] Command available: `php artisan list | grep install`
- [ ] Installation works: Files modified, database migrated, test user created
- [ ] Login works: Can access dashboard with test credentials
- [ ] All commits have proper messages with attribution

## Success Criteria

Installation command is successful when:
1. ✅ Single command: `php artisan base-tenant:install`
2. ✅ Interactive questions with sensible defaults
3. ✅ Clear preview before any changes
4. ✅ Progress shown for all steps
5. ✅ Success with credentials displayed
6. ✅ User can login immediately
7. ✅ Takes < 2 minutes total

## Commit Summary

Total commits: 10
- Task 1: ConflictDetector
- Task 2: UserModelManager + stub
- Task 3: EnvironmentManager
- Task 4: MigrationRunner
- Task 5: TestUserSeeder
- Task 6: InstallCommand Phase 1
- Task 7: InstallCommand Phase 2
- Task 8: InstallCommand Phase 3
- Task 9: Register command
- Task 10: Update README

All changes in base-tenant package at `/Users/nicolascantelipenic/Sites/base-tenant`.
