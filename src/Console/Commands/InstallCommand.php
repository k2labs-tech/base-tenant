<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Console\Support\ConflictDetector;
use Base\Tenant\Console\Support\DatabaseConfigurator;
use Base\Tenant\Console\Support\EnvironmentManager;
use Base\Tenant\Console\Support\MigrationRunner;
use Base\Tenant\Console\Support\StylesheetManager;
use Base\Tenant\Console\Support\UserModelManager;
use Base\Tenant\Database\Seeders\TestUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:install
        {--database : Ask for the database connection even if the current one works}';

    protected $description = 'Install and configure the Base Tenant package';

    protected ConflictDetector $conflictDetector;

    protected UserModelManager $userModelManager;

    protected EnvironmentManager $environmentManager;

    protected MigrationRunner $migrationRunner;

    protected DatabaseConfigurator $databaseConfigurator;

    /** @var array<string, string>|null */
    protected ?array $databaseConfig = null;

    // Installation options
    protected bool $multiTeam = false;

    protected bool $subscriptions = false;

    protected bool $createTestUser = true;

    protected array $conflictingMigrations = [];

    protected bool $userModelConflict = false;

    protected bool $alreadyInstalled = false;

    // Third-party API keys
    protected string $stripeKey = '';

    protected string $stripeSecret = '';

    protected string $stripeProduct = '';

    protected string $stripePrice = '';

    protected string $flareKey = '';

    protected string $fluxProEmail = '';

    protected string $fluxProLicenseKey = '';

    protected bool $fluxProNeedsInstall = false;

    public function handle(): int
    {
        $basePath = base_path();

        $this->conflictDetector = new ConflictDetector($basePath);
        $this->userModelManager = new UserModelManager($basePath);
        $this->environmentManager = new EnvironmentManager($basePath);
        $this->migrationRunner = new MigrationRunner;
        $this->databaseConfigurator = new DatabaseConfigurator($basePath, $this->environmentManager);

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
        $this->askDatabaseConnection();

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

        if ($this->subscriptions) {
            $this->stripeKey = $this->ask('  Stripe Publishable Key (pk_...)') ?? '';
            $this->stripeSecret = $this->ask('  Stripe Secret Key (sk_...)') ?? '';
            $this->stripeProduct = $this->ask('  Stripe Default Product ID (prod_...)') ?? '';
            $this->stripePrice = $this->ask('  Stripe Default Price ID (price_...)') ?? '';
        }

        // Test user question
        $this->createTestUser = $this->components->confirm(
            '👤 Create Test User? (test@example.com / password)',
            true
        );

        $this->newLine();
        $this->components->info('Third-party Services (optional)');
        $this->newLine();

        // Flare
        $this->flareKey = $this->ask('  Flare API Key (leave empty to skip)') ?? '';

        // Flux UI Pro
        $this->components->info('Flux UI Pro is optional. This package uses only free Flux components.');

        if ($this->components->confirm('  Install Flux UI Pro? (requires a license)', false)) {
            $this->fluxProEmail = $this->ask('  Flux Pro email') ?? '';
            $this->fluxProLicenseKey = $this->ask('  Flux Pro license key') ?? '';
        }

        $this->newLine();
    }

    /**
     * Ask for the database connection, unless the one already configured works
     * and the user did not ask to change it.
     *
     * This runs before anything else because the installer migrates and seeds:
     * finding out afterwards that it went to the wrong database is expensive to
     * undo.
     */
    protected function askDatabaseConnection(): void
    {
        $this->components->info('Database');
        $this->newLine();

        $works = $this->databaseConfigurator->currentConnectionWorks();

        if ($works && ! $this->option('database')) {
            $this->components->twoColumnDetail(
                '  Current connection',
                '<fg=green>'.$this->databaseConfigurator->describe().'</>'
            );

            if (! $this->components->confirm('  Use a different database?', false)) {
                $this->newLine();

                return;
            }
        }

        if (! $works) {
            $this->components->warn('  Could not connect with the current settings.');
            $this->line('  <fg=gray>'.$this->databaseConfigurator->lastError().'</>');
            $this->newLine();
        }

        $this->databaseConfig = $this->promptForDatabaseUntilItConnects();

        $this->newLine();
    }

    /**
     * @return array<string, string>|null
     */
    protected function promptForDatabaseUntilItConnects(): ?array
    {
        while (true) {
            $config = $this->promptForDatabase();

            if ($this->databaseConfigurator->test($config)) {
                $this->components->info('  Connection successful.');

                return $config;
            }

            $this->components->error('  Could not connect: '.$this->databaseConfigurator->lastError());

            if ($this->option('no-interaction')
                || ! $this->components->confirm('  Try again?', true)) {
                $this->components->warn('  Continuing with the current settings. Migrations may fail.');

                return null;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function promptForDatabase(): array
    {
        $current = $this->databaseConfigurator->currentConfig();

        $driver = $this->choice(
            '  Database driver',
            DatabaseConfigurator::DRIVERS,
            array_search($current['driver'], DatabaseConfigurator::DRIVERS, true) ?: 0
        );

        if ($driver === 'sqlite') {
            $path = (string) $this->ask('  Database file', $this->databaseConfigurator->defaultSqlitePath());

            $this->ensureSqliteFile($path);

            return ['driver' => 'sqlite', 'database' => $path, 'host' => '', 'port' => '', 'username' => '', 'password' => ''];
        }

        return [
            'driver' => $driver,
            'host' => (string) $this->ask('  Host', $current['host'] ?: '127.0.0.1'),
            'port' => (string) $this->ask('  Port', $current['port'] ?: $this->databaseConfigurator->defaultPort($driver)),
            'database' => (string) $this->ask('  Database name', $current['database'] ?: 'base_tenant'),
            'username' => (string) $this->ask('  Username', $current['username'] ?: 'root'),
            'password' => (string) ($this->secret('  Password (leave empty for none)') ?? ''),
        ];
    }

    /**
     * An empty SQLite file is a valid, empty database, and connecting to one
     * that does not exist fails. Without this the installer cannot get past the
     * question it just asked: the file is only created when the settings are
     * applied, and they are only applied once the connection works.
     */
    protected function ensureSqliteFile(string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $relative = $this->databaseConfigurator->relativePath($path);

        if ($this->option('no-interaction')
            || $this->components->confirm("  {$relative} does not exist. Create it?", true)) {
            $this->databaseConfigurator->ensureSqliteFileExists($path);
            $this->components->info("  Created {$relative}.");
        }
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
            $this->createTestUser ? '<fg=green>Yes (test@example.com)</>' : '<fg=yellow>No</>'
        );
        $this->components->twoColumnDetail(
            '  Database',
            '<fg=green>'.$this->databaseConfigurator->describe($this->databaseConfig).'</>'
        );
        $this->newLine();

        // Files to be modified
        $this->components->info('Files to be modified:');

        foreach ($this->conflictingMigrations as $migration) {
            $this->components->twoColumnDetail('  <fg=yellow>DELETE</>', $migration);
        }

        if (! $this->userModelConflict) {
            $this->components->twoColumnDetail('  <fg=yellow>REPLACE</>', 'app/Models/User.php');
        }

        $this->components->twoColumnDetail('  <fg=yellow>REPLACE</>', 'routes/web.php');
        $this->components->twoColumnDetail('  <fg=green>CREATE</>', 'config/base-tenant.php');
        $this->components->twoColumnDetail('  <fg=blue>UPDATE</>', '.env (BASE_TENANT_* variables)');
        $this->newLine();

        // Third-party services
        if ($this->stripeKey || $this->flareKey || $this->fluxProEmail) {
            $this->components->info('Third-party Services:');

            if ($this->stripeKey) {
                $this->components->twoColumnDetail('  Stripe Key', '<fg=green>'.substr($this->stripeKey, 0, 12).'...</>');
            }

            if ($this->stripeProduct) {
                $this->components->twoColumnDetail('  Stripe Product', "<fg=green>{$this->stripeProduct}</>");
            }

            if ($this->stripePrice) {
                $this->components->twoColumnDetail('  Stripe Price', "<fg=green>{$this->stripePrice}</>");
            }

            if ($this->flareKey) {
                $this->components->twoColumnDetail('  Flare', '<fg=green>'.substr($this->flareKey, 0, 8).'...</>');
            }

            if ($this->fluxProEmail) {
                $this->components->twoColumnDetail('  Flux Pro', "<fg=green>{$this->fluxProEmail}</>");
            }

            $this->newLine();
        }

        // Database operations
        $this->components->info('Database operations:');
        $this->components->twoColumnDetail('  <fg=green>✓</>', 'Run 17 package migrations');
        $this->components->twoColumnDetail('  <fg=green>✓</>', 'Seed 7 default roles');
        $this->components->twoColumnDetail('  <fg=green>✓</>', 'Create admin user ('.$this->adminCredentials()['email'].')');
        if ($this->createTestUser) {
            $this->components->twoColumnDetail(
                '  <fg=green>✓</>',
                'Create test account ('.TestUserSeeder::ACCOUNT.') and user ('.TestUserSeeder::EMAIL.')'
            );
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
        // Before any step runs, so nothing in the installation is resolved
        // against the database we are moving away from.
        if ($this->databaseConfig !== null) {
            $this->databaseConfigurator->apply($this->databaseConfig);
        }

        $this->components->twoColumnDetail('╔══════════════════════════════════════════════════════════════╗', '');
        $this->components->twoColumnDetail('║  Installing Base Tenant...', '║');
        $this->components->twoColumnDetail('╚══════════════════════════════════════════════════════════════╝', '');
        $this->newLine();

        $hasFluxPro = $this->fluxProEmail && $this->fluxProLicenseKey;

        $step = 1;
        $totalSteps = $hasFluxPro ? 9 : 8;

        try {
            // Step 1: Clean migrations, routes, and views
            $this->components->task("[{$step}/{$totalSteps}] Cleaning conflicting files", function () {
                $this->cleanConflictingMigrations();
                $this->cleanConflictingRoutes();
                $this->cleanConflictingViews();

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

            // Step 5: Configure Flux Pro (if credentials provided)
            if ($hasFluxPro) {
                $this->components->task("[{$step}/{$totalSteps}] Configuring Flux UI Pro", function () {
                    return $this->configureFluxPro();
                });
                $step++;
            }

            // Run migrations
            $this->components->task("[{$step}/{$totalSteps}] Running migrations", function () {
                return $this->runMigrations();
            });
            $step++;

            // Step 6: Seed roles
            $this->components->task("[{$step}/{$totalSteps}] Seeding permissions, roles and menus", function () {
                return $this->seedRoles();
            });
            $step++;

            // Step 7: Create admin user
            $this->components->task("[{$step}/{$totalSteps}] Creating admin user", function () {
                return $this->createAdminUser();
            });
            $step++;

            // Step 8: Create test data
            $this->components->task("[{$step}/{$totalSteps}] Creating test data", function () {
                return $this->createTestData();
            });

            // Generate setup report
            $this->generateSetupReport();

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
     * Clean conflicting views from starter kits
     */
    protected function cleanConflictingViews(): bool
    {
        // Remove starter kit layouts
        $paths = [
            resource_path('views/components/layouts/app'),
            resource_path('views/components/layouts/app.blade.php'),
            resource_path('views/components/layouts/auth'),
            resource_path('views/components/layouts/auth.blade.php'),
            resource_path('views/dashboard.blade.php'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                if (File::isDirectory($path)) {
                    File::deleteDirectory($path);
                } else {
                    File::delete($path);
                }
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

        // Note: Translations are NOT published by default.
        // The package uses namespaced translations (base-tenant::xxx)
        // Users can optionally publish to customize: php artisan vendor:publish --tag=base-tenant-lang

        $this->pointUserModelAtApplication();

        // Update app.css to include base-tenant views
        $this->updateAppCss();

        // Update bootstrap/app.php to configure auth redirects
        $this->updateBootstrap();

        return true;
    }

    /**
     * Update environment variables
     */
    /**
     * The installer writes `app/Models/User.php`, so the package must resolve
     * that class and not its own. It also has to match `config/auth.php`, or
     * the permission layer cannot infer a guard for the model.
     *
     * Matched by pattern rather than by literal string: the published config
     * may reference the model fully qualified or through a `use` statement,
     * depending on how it was last formatted.
     */
    protected function pointUserModelAtApplication(): void
    {
        $configPath = config_path('base-tenant.php');

        if (! File::exists($configPath)) {
            return;
        }

        $userModel = $this->userModelManager->applicationUserModel();

        $updated = preg_replace(
            "/('user'\s*=>\s*env\('BASE_TENANT_USER_MODEL',\s*)(?:\\\\?[A-Za-z0-9_\\\\]*User)(::class\))/",
            '${1}\\'.$userModel.'${2}',
            File::get($configPath),
            1
        );

        File::put($configPath, $updated);
    }

    protected function updateEnvironment(): bool
    {
        $this->environmentManager->addBaseTenantVariables(
            multiTeam: $this->multiTeam,
            subscriptions: $this->subscriptions,
            stripeKey: $this->stripeKey,
            stripeSecret: $this->stripeSecret,
            stripeProduct: $this->stripeProduct,
            stripePrice: $this->stripePrice,
            flareKey: $this->flareKey,
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
     * Seed the permission catalogue, the global roles and the product menus
     */
    protected function seedRoles(): bool
    {
        $this->migrationRunner->seedDefaultRoles();

        return true;
    }

    /**
     * Create default admin user
     */
    protected function createAdminUser(): bool
    {
        $this->migrationRunner->createAdminUser();

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
     * Generate setup report documenting installation choices
     */
    protected function generateSetupReport(): void
    {
        $date = now()->format('Y-m-d H:i:s');
        $appName = config('app.name', 'Laravel');
        $environment = app()->environment();

        $stripeStatus = ! $this->subscriptions
            ? 'Disabled'
            : ($this->stripeKey ? 'Configured' : 'Enabled (keys pending)');

        $admin = $this->adminCredentials();

        $report = <<<MD
        # {$appName} - Base Tenant Setup Report

        > Generated on {$date}

        ## Configuration Summary

        | Setting | Value |
        |---|---|
        | Multi-Team Mode | {$this->formatBool($this->multiTeam)} |
        | Subscriptions | {$stripeStatus} |
        | Test User Created | {$this->formatBool($this->createTestUser)} |
        | Flare Error Tracking | {$this->formatOptional($this->flareKey)} |
        | Flux UI Pro | {$this->formatOptional($this->fluxProEmail)} |
        | Environment | {$environment} |

        ## User Credentials

        ### Admin (always created)

        | | |
        |---|---|
        | Email | `{$admin['email']}` |
        | Password | `{$admin['password']}` |
        | Role | `administrator` |
        | Account | None (system admin) |

        MD;

        if ($this->createTestUser) {
            $testEmail = TestUserSeeder::EMAIL;
            $testPassword = TestUserSeeder::PASSWORD;
            $testRole = TestUserSeeder::ROLE;
            $testAccount = TestUserSeeder::ACCOUNT;

            $report .= <<<MD
            ### Test User

            | | |
            |---|---|
            | Email | `{$testEmail}` |
            | Password | `{$testPassword}` |
            | Role | `{$testRole}` |
            | Account | {$testAccount} |

            MD;
        }

        if ($this->subscriptions) {
            $productDisplay = $this->stripeProduct ?: '`not set`';
            $priceDisplay = $this->stripePrice ?: '`not set`';

            $report .= <<<MD
            ## Stripe Subscriptions

            ### Environment Variables

            ```env
            STRIPE_KEY={$this->maskKey($this->stripeKey)}
            STRIPE_SECRET={$this->maskKey($this->stripeSecret)}
            BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT={$productDisplay}
            BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE={$priceDisplay}
            BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14
            ```

            ### Stripe CLI (Webhooks)

            To receive Stripe webhook events locally (required for subscription activation after checkout), install and configure the Stripe CLI:

            **1. Install Stripe CLI**

            ```bash
            # macOS
            brew install stripe/stripe-cli/stripe

            # Linux
            curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
            echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
            sudo apt update && sudo apt install stripe

            # Windows
            winget install Stripe.StripeCLI
            ```

            **2. Authenticate**

            ```bash
            stripe login
            ```

            **3. Forward webhooks to your local server**

            ```bash
            stripe listen --forward-to localhost:8000/stripe/webhook
            ```

            This will output a webhook signing secret (`whsec_...`). Add it to your `.env`:

            ```env
            STRIPE_WEBHOOK_SECRET=whsec_...
            ```

            **4. Keep it running** while testing checkout flows. The CLI forwards events like `checkout.session.completed` to Laravel Cashier, which updates the subscription status automatically.

            ### Subscription Flow

            1. Non-subscribed user logs in and is redirected to `/checkout`
            2. Stripe Checkout opens with the configured product/price
            3. After payment, Stripe sends a webhook to `/stripe/webhook`
            4. Laravel Cashier processes the webhook and activates the subscription
            5. User is redirected to the dashboard

            ### Non-Production Bypass

            In non-production environments (`local`, `testing`), if Stripe is not fully configured, the subscription check is bypassed automatically. Users can access the dashboard without a subscription.

            MD;
        }

        $report .= <<<'MD'
        ## Middleware Reference

        | Alias | Class | Purpose |
        |---|---|---|
        | `base-tenant.subscription` | `HasSubscription` | Requires active subscription (bypassed for admins) |
        | `base-tenant.no-subscription` | `DoesNotHaveSubscription` | Only for users without subscription (checkout) |
        | `base-tenant.locale` | `SetLocale` | Sets user locale |
        | `base-tenant.password-changed` | `EnsurePasswordChanged` | Forces password change on first login |
        | `base-tenant.account-context` | `SetAccountContext` | Sets current account in session |
        | `base-tenant.feature` | `HasFeature` | Plan feature gate |

        ## Key Routes

        | Route | Name | Middleware |
        |---|---|---|
        | `/login` | `base-tenant.login` | `guest` |
        | `/register` | `base-tenant.register` | `guest` |
        | `/dashboard` | `base-tenant.dashboard` | `auth, verified, subscription` |
        | `/users` | `base-tenant.users.index` | `auth, verified, subscription` |
        | `/accounts` | `base-tenant.accounts.index` | `auth, verified, subscription` |
        | `/roles` | `base-tenant.roles.index` | `auth, verified, subscription` |
        | `/navigation` | `base-tenant.menus.index` | `auth, verified, subscription` |
        | `/checkout` | `base-tenant.checkout` | `auth, verified, no-subscription` |
        | `/billing` | `base-tenant.billing` | `auth, verified, subscription` |

        ## Artisan Commands

        ```bash
        # Sync permissions and roles from config to database
        php artisan k2labs-base:sync-roles

        # Sync the menus declared in code to the database
        php artisan k2labs-base:sync-menus

        # Re-run installation
        php artisan k2labs-base:install

        # Prune old activity log entries
        php artisan k2labs-base:prune-activity-log
        ```

        ## Useful Links

        - [Installation Guide](vendor/k2labs/base-tenant/docs/INSTALLATION.md)
        - [Usage Guide](vendor/k2labs/base-tenant/docs/USAGE.md)
        - [Frontend Guide](vendor/k2labs/base-tenant/docs/FRONTEND.md)
        - [Stripe Dashboard](https://dashboard.stripe.com)
        - [Stripe CLI Docs](https://docs.stripe.com/stripe-cli)
        - [Laravel Cashier Docs](https://laravel.com/docs/billing)
        - [Flux UI Docs](https://fluxui.dev/docs)
        - [Flare Dashboard](https://flareapp.io)

        MD;

        // Remove leading indentation from heredoc
        $report = preg_replace('/^ {8}/m', '', $report);

        $docsPath = base_path('docs');

        if (! File::isDirectory($docsPath)) {
            File::makeDirectory($docsPath, 0755, true);
        }

        File::put($docsPath.'/SETUP.md', $report);

        // Copy package docs to host project
        $packageDocsPath = __DIR__.'/../../../docs';

        if (File::isDirectory($packageDocsPath)) {
            $sourcePath = $packageDocsPath.'/INSTALLATION.md';

            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $docsPath.'/BASE_TENANT_INSTALLATION.md');
            }
        }

        $this->publishAgentDocs();
    }

    /**
     * Put the agent documentation in the project and point its root stub at it.
     *
     * A copy rather than a link into `vendor/`: the project can then edit it,
     * and `k2labs-base:publish-agent-docs` keeps its own section of the stub
     * between markers so a later `composer update` refreshes the package's half
     * without touching what the project wrote around it.
     */
    protected function publishAgentDocs(): void
    {
        // Published by default when nobody is there to answer: a project that
        // did not want it can delete a directory, and one that never knew the
        // documentation existed gets an agent reimplementing the package.
        $publish = $this->option('no-interaction')
            || $this->components->confirm('Publish the AI agent documentation into this project?', true);

        if ($publish) {
            $this->call('k2labs-base:publish-agent-docs');
        }
    }

    protected function formatBool(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    protected function formatOptional(string $value): string
    {
        return $value ? 'Configured' : 'Not configured';
    }

    protected function maskKey(string $key): string
    {
        if (! $key) {
            return '`not set`';
        }

        return substr($key, 0, 8).'...';
    }

    /**
     * Configure Flux UI Pro auth and repository in host app
     */
    protected function configureFluxPro(): bool
    {
        $basePath = base_path();

        // Configure auth.json
        $authPath = $basePath.'/auth.json';
        $auth = [];

        if (File::exists($authPath)) {
            $auth = json_decode(File::get($authPath), true) ?? [];
        }

        $auth['http-basic']['composer.fluxui.dev'] = [
            'username' => $this->fluxProEmail,
            'password' => $this->fluxProLicenseKey,
        ];

        File::put($authPath, json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        // Add repository to host's composer.json
        $composerPath = $basePath.'/composer.json';

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $repositories = $composer['repositories'] ?? [];

            $hasFluxRepo = false;
            foreach ($repositories as $repo) {
                if (isset($repo['url']) && str_contains($repo['url'], 'composer.fluxui.dev')) {
                    $hasFluxRepo = true;
                    break;
                }
            }

            if (! $hasFluxRepo) {
                $composer['repositories'][] = [
                    'type' => 'composer',
                    'url' => 'https://composer.fluxui.dev',
                ];
            }

            // Flux Pro is an optional dependency: this package uses only free
            // Flux components, so it is required here rather than upstream.
            if (! isset($composer['require']['livewire/flux-pro'])) {
                $composer['require']['livewire/flux-pro'] = '^2.4';
            }

            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $this->fluxProNeedsInstall = true;
        }

        return true;
    }

    /**
     * What `AdminUserSeeder` will actually create, so the report never
     * promises credentials the environment has overridden.
     *
     * @return array{name: string, email: string, password: string}
     */
    protected function adminCredentials(): array
    {
        return [
            'name' => (string) config('base-tenant.admin.name', 'Administrator'),
            'email' => (string) config('base-tenant.admin.email', 'admin@example.com'),
            'password' => (string) config('base-tenant.admin.password', 'secret123'),
        ];
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

        $admin = $this->adminCredentials();

        $this->components->info('Admin Credentials:');
        $this->components->twoColumnDetail('  Email', $admin['email']);
        $this->components->twoColumnDetail('  Password', $admin['password']);
        $this->components->twoColumnDetail('  Role', 'administrator (platform staff, no account)');
        $this->newLine();

        if ($this->createTestUser) {
            $this->components->info('Test User Credentials:');
            $this->components->twoColumnDetail('  Email', TestUserSeeder::EMAIL);
            $this->components->twoColumnDetail('  Password', TestUserSeeder::PASSWORD);
            $this->components->twoColumnDetail('  Role', TestUserSeeder::ROLE);
            $this->components->twoColumnDetail('  Account', TestUserSeeder::ACCOUNT);
            $this->newLine();
        }

        $this->components->info('Next Steps:');
        $step = 1;

        if ($this->fluxProNeedsInstall) {
            $this->line("  {$step}. Pull in Flux Pro: <fg=green>composer update livewire/flux-pro</>");
            $step++;
        }

        $this->line("  {$step}. Start server: <fg=green>php artisan serve</>");
        $step++;
        $this->line("  {$step}. Rebuild from scratch at any time: <fg=green>php artisan migrate:fresh --seed</>");
        $step++;
        $this->line("  {$step}. Visit: <fg=green>http://127.0.0.1:8000/login</>");
        $step++;

        if ($this->subscriptions && $this->stripeKey) {
            $this->line("  {$step}. Start Stripe CLI: <fg=green>stripe listen --forward-to localhost:8000/stripe/webhook</>");
            $step++;
        }

        $this->line("  {$step}. Review setup report: <fg=green>docs/SETUP.md</>");
        $step++;
        $this->line("  {$step}. AI agent documentation: <fg=green>docs/agents/base/00-index.md</>");
        $this->newLine();

        if ($this->subscriptions && (! $this->stripeKey || ! $this->stripeProduct)) {
            $missingKeys = [];

            if (! $this->stripeKey) {
                $missingKeys[] = 'STRIPE_KEY, STRIPE_SECRET';
            }

            if (! $this->stripeProduct) {
                $missingKeys[] = 'BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT, BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE';
            }

            $this->components->warn('Missing Stripe config in .env: '.implode(', ', $missingKeys));
            $this->newLine();
        }
    }

    /**
     * Conecta el app.css de la aplicación con el tema y las vistas del paquete.
     */
    protected function updateAppCss(): void
    {
        $cssPath = resource_path('css/app.css');

        if (! File::exists($cssPath)) {
            return;
        }

        $original = File::get($cssPath);
        $updated = (new StylesheetManager)->apply($original);

        if ($updated === $original) {
            return;
        }

        File::put($cssPath, $updated);

        $this->components->info('Updated app.css with base-tenant configuration');
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
        $middleware->redirectGuestsTo(fn () => route(\'base-tenant.login\'));
        $middleware->redirectUsersTo(fn () => route(\'base-tenant.dashboard\'));
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
        $middleware->redirectGuestsTo(fn () => route(\'base-tenant.login\'));
        $middleware->redirectUsersTo(fn () => route(\'base-tenant.dashboard\'));';

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
