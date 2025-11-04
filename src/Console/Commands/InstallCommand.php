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

    /**
     * Phase 3: Execute installation (placeholder)
     */
    protected function executeInstallation(): int
    {
        // Will implement in next task
        return self::SUCCESS;
    }
}
