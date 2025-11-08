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
    protected $signature = 'base-tenant:install';

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

        $this->components->twoColumnDetail('  <fg=yellow>REPLACE</>', 'routes/web.php');
        $this->components->twoColumnDetail('  <fg=green>CREATE</>', 'config/base-tenant.php');
        $this->components->twoColumnDetail('  <fg=green>CREATE</>', 'public/vendor/base-tenant/');
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
            // Step 1: Clean migrations and routes
            $this->components->task("[{$step}/{$totalSteps}] Cleaning conflicting files", function () {
                $this->cleanConflictingMigrations();
                $this->cleanConflictingRoutes();
                return true;
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
     * Clean conflicting routes
     */
    protected function cleanConflictingRoutes(): bool
    {
        $webRoutesPath = base_path('routes/web.php');

        if (File::exists($webRoutesPath)) {
            $stubPath = __DIR__.'/../../../stubs/web.php.stub';
            $stub = File::get($stubPath);
            File::put($webRoutesPath, $stub);
        }

        // Replace FortifyServiceProvider to disable Fortify routes
        $fortifyProviderPath = app_path('Providers/FortifyServiceProvider.php');
        if (File::exists($fortifyProviderPath)) {
            $stubPath = __DIR__.'/../../../stubs/FortifyServiceProvider.php.stub';
            $stub = File::get($stubPath);
            File::put($fortifyProviderPath, $stub);
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

        // Publish assets
        $this->callSilent('vendor:publish', [
            '--tag' => 'base-tenant-assets',
            '--force' => true,
        ]);

        // Publish translations
        $this->callSilent('vendor:publish', [
            '--tag' => 'base-tenant-lang',
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

        // Update app.css to include base-tenant views
        $this->updateAppCss();

        // Update bootstrap/app.php to configure auth redirects
        $this->updateBootstrap();

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

    /**
     * Update app.css to include base-tenant views in Tailwind scanning
     */
    protected function updateAppCss(): void
    {
        $cssPath = resource_path('css/app.css');

        if (! File::exists($cssPath)) {
            return;
        }

        $content = File::get($cssPath);

        // Check if already configured
        if (str_contains($content, 'vendor/base/tenant/resources/views')) {
            return;
        }

        // Add source paths for both development (symlink) and production
        $sourcePaths = "\n@source '../../base-tenant/resources/views/**/*.blade.php';\n@source '../../vendor/base/tenant/resources/views/**/*.blade.php';";

        // Insert after the @source '../views'; line
        if (str_contains($content, "@source '../views';")) {
            $content = str_replace(
                "@source '../views';",
                "@source '../views';" . $sourcePaths,
                $content
            );

            File::put($cssPath, $content);
            $this->components->info('Updated app.css to include base-tenant views');
        }
    }

    /**
     * Update bootstrap/app.php to configure authentication redirects
     */
    protected function updateBootstrap(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (! File::exists($bootstrapPath)) {
            return;
        }

        $content = File::get($bootstrapPath);

        // Check if already configured
        if (str_contains($content, 'redirectGuestsTo') || str_contains($content, 'base-tenant.login')) {
            return;
        }

        // Find the withMiddleware method and add the redirects
        $search = '->withMiddleware(function (Middleware $middleware): void {
        //
    })';

        $replace = '->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(\'base-tenant.login\');
        $middleware->redirectUsersTo(\'base-tenant.dashboard\');
    })';

        if (str_contains($content, $search)) {
            $content = str_replace($search, $replace, $content);
            File::put($bootstrapPath, $content);
            $this->components->info('Updated bootstrap/app.php to configure auth redirects');
        } else {
            // Try alternative pattern without the empty comment
            $search2 = '->withMiddleware(function (Middleware $middleware): void {';

            if (str_contains($content, $search2)) {
                // Insert after the opening brace
                $replace2 = '->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(\'base-tenant.login\');
        $middleware->redirectUsersTo(\'base-tenant.dashboard\');';

                $content = preg_replace(
                    '/->withMiddleware\(function \(Middleware \$middleware\): void \{/',
                    $replace2,
                    $content,
                    1
                );

                File::put($bootstrapPath, $content);
                $this->components->info('Updated bootstrap/app.php to configure auth redirects');
            } else {
                $this->components->warn('Could not automatically update bootstrap/app.php. Please add auth redirects manually.');
            }
        }
    }
}
