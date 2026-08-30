<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Console\Support\CodeTransformer;
use Base\Tenant\Console\Support\ScaffoldConflictDetector;
use Base\Tenant\Console\Support\ScaffoldPlan;
use Base\Tenant\Console\Support\ServiceProviderGenerator;
use Base\Tenant\Console\Support\StoredLabelRewriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies the package code into the application and rewrites it to belong there.
 *
 * After this the application runs on its own copy while the package is still
 * installed, which is the point: you can read the diff, run the suite and only
 * then decide to `k2labs-base:eject`.
 */
class ScaffoldCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:scaffold
        {--dry-run : List what would be written without touching anything}
        {--overwrite : Replace existing files instead of asking}
        {--skip-existing : Keep existing files instead of asking}
        {--force : Run even if the installation state is not "installed"}';

    protected $description = 'Copy the package code into your application so you own it';

    protected CodeTransformer $transformer;

    protected ScaffoldPlan $plan;

    protected ScaffoldConflictDetector $detector;

    public function handle(): int
    {
        $packagePath = dirname(__DIR__, 3);

        $this->transformer = new CodeTransformer;
        $this->plan = new ScaffoldPlan($packagePath, base_path());
        $this->detector = new ScaffoldConflictDetector;

        if (! $this->verifyState()) {
            return self::FAILURE;
        }

        $files = $this->plan->files();

        if ($files === []) {
            $this->components->error('Nothing to scaffold. Is the package installed correctly?');

            return self::FAILURE;
        }

        $this->showPreview($files);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $resolution = $this->resolveConflicts($files);

        if ($resolution === null) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        $written = $this->writeFiles($files, $resolution);

        $this->generateServiceProvider($packagePath);
        $this->registerServiceProvider();
        $this->rewriteConfig();
        $this->rewriteStoredLabels();
        $this->setInstallationState('scaffolded');

        $this->showSummary($written);

        return self::SUCCESS;
    }

    protected function verifyState(): bool
    {
        $state = config('base-tenant.installation_state', 'installed');

        if ($state === 'scaffolded' && ! $this->option('force')) {
            $this->components->warn('Already scaffolded. Re-run with --force to refresh the copied code.');

            return false;
        }

        if ($state === 'ejected') {
            $this->components->error('This project has already ejected the package.');

            return false;
        }

        if ($state === 'fresh' && ! $this->option('force')) {
            $this->components->error('Run `php artisan k2labs-base:install` first.');

            return false;
        }

        if (! File::exists(config_path('base-tenant.php'))) {
            $this->components->error('config/base-tenant.php is missing. Run `php artisan vendor:publish --tag=base-tenant-config` first.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>  $files
     */
    protected function showPreview(array $files): void
    {
        $this->newLine();
        $this->components->info('Scaffold plan');

        $rows = [];

        foreach ($this->plan->filesByGroup() as $group => $groupFiles) {
            $rows[] = [$group, count($groupFiles), dirname($groupFiles[0]['relative'])];
        }

        $this->table(['Group', 'Files', 'Destination'], $rows);

        $this->components->twoColumnDetail('<fg=gray>Total files</>', (string) count($files));
        $this->components->twoColumnDetail('<fg=gray>Namespace</>', 'Base\\Tenant\\ → App\\');
        $this->components->twoColumnDetail('<fg=gray>Views and translations</>', 'base-tenant:: → tenant::');
        $this->components->twoColumnDetail('<fg=gray>Blade components</>', 'x-base-tenant::* → x-tenant.*');
        $this->components->twoColumnDetail('<fg=gray>Service provider</>', 'app/Providers/TenancyServiceProvider.php');
    }

    /**
     * @param  array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>  $files
     */
    protected function resolveConflicts(array $files): ?string
    {
        if ($this->option('overwrite')) {
            return ScaffoldConflictDetector::OVERWRITE;
        }

        if ($this->option('skip-existing')) {
            return ScaffoldConflictDetector::SKIP;
        }

        $conflicts = $this->detector->meaningfulConflicts($files, $this->transformer);

        if ($conflicts === []) {
            return ScaffoldConflictDetector::OVERWRITE;
        }

        $this->newLine();
        $this->components->warn(count($conflicts).' destination files already exist and differ:');

        foreach (array_slice($conflicts, 0, 15) as $conflict) {
            $this->line("  <fg=yellow>{$conflict['relative']}</>");
        }

        if (count($conflicts) > 15) {
            $this->line('  <fg=gray>… and '.(count($conflicts) - 15).' more</>');
        }

        $this->newLine();

        return match ($this->choice('How should these be handled?', [
            'Overwrite them',
            'Keep the existing files',
            'Cancel',
        ], 2)) {
            'Overwrite them' => ScaffoldConflictDetector::OVERWRITE,
            'Keep the existing files' => ScaffoldConflictDetector::SKIP,
            default => null,
        };
    }

    /**
     * @param  array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>  $files
     * @return array{written: int, skipped: int}
     */
    protected function writeFiles(array $files, string $resolution): array
    {
        $written = 0;
        $skipped = 0;

        $this->newLine();

        $this->withProgressBar($files, function (array $file) use ($resolution, &$written, &$skipped): void {
            $exists = File::exists($file['destination']);

            if ($exists && $this->isJsonTranslation($file)) {
                $this->mergeJsonTranslation($file);
                $written++;

                return;
            }

            if ($exists && $resolution === ScaffoldConflictDetector::SKIP) {
                $skipped++;

                return;
            }

            $contents = File::get($file['source']);

            if ($file['transform']) {
                $contents = $this->transformer->transform($contents);
            }

            File::ensureDirectoryExists(dirname($file['destination']));
            File::put($file['destination'], $contents);

            $written++;
        });

        $this->newLine(2);

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * @param  array{source: string, destination: string, relative: string, transform: bool, group: string}  $file
     */
    protected function isJsonTranslation(array $file): bool
    {
        return $file['group'] === 'JSON translations';
    }

    /**
     * Strings the application already translates win, so scaffolding never
     * changes wording you had chosen.
     *
     * @param  array{source: string, destination: string, relative: string, transform: bool, group: string}  $file
     */
    protected function mergeJsonTranslation(array $file): void
    {
        $incoming = json_decode(File::get($file['source']), true) ?: [];
        $existing = json_decode(File::get($file['destination']), true) ?: [];

        $merged = [...$incoming, ...$existing];

        ksort($merged);

        File::put($file['destination'], json_encode(
            $merged,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )."\n");
    }

    protected function generateServiceProvider(string $packagePath): void
    {
        $generator = new ServiceProviderGenerator(
            $this->transformer,
            "{$packagePath}/stubs/TenancyServiceProvider.php.stub"
        );

        $path = app_path('Providers/TenancyServiceProvider.php');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $generator->render());

        $this->components->task('Generating app/Providers/TenancyServiceProvider.php');
    }

    /**
     * Laravel 11+ keeps application providers in bootstrap/providers.php.
     */
    protected function registerServiceProvider(): void
    {
        $path = base_path('bootstrap/providers.php');
        $entry = 'App\\Providers\\TenancyServiceProvider::class,';

        if (! File::exists($path)) {
            $this->components->warn('bootstrap/providers.php not found: register TenancyServiceProvider manually.');

            return;
        }

        $contents = File::get($path);

        if (str_contains($contents, 'TenancyServiceProvider')) {
            return;
        }

        $contents = preg_replace('/^(return \[\n)/m', "$1    {$entry}\n", $contents, 1);

        File::put($path, $contents);

        $this->components->task('Registering TenancyServiceProvider');
    }

    /**
     * The published config still points at the package's classes, and at its
     * views: `layouts.app` and the permission labels are `base-tenant::`
     * references that stop resolving once the code lives in the application.
     */
    protected function rewriteConfig(): void
    {
        $path = config_path('base-tenant.php');

        File::put($path, $this->transformer->transform(File::get($path)));

        $this->components->task('Rewriting config/base-tenant.php');
    }

    /**
     * Las etiquetas de los menús están guardadas en la base de datos, no en el
     * código, así que el transformador de ficheros no las alcanza. Sin este
     * paso la aplicación separada muestra `base-tenant::app.navigation.…` en
     * crudo en toda la barra lateral.
     *
     * No es motivo para abortar: el código ya está copiado y una base de datos
     * inaccesible se arregla después con `k2labs-base:sync-menus`.
     */
    protected function rewriteStoredLabels(): void
    {
        try {
            $reescritas = (new StoredLabelRewriter)->rewrite();
        } catch (\Throwable $exception) {
            $this->components->warn(
                'Stored menu labels were left alone ('.$exception->getMessage().'). '.
                'Run `php artisan k2labs-base:sync-menus` once the database is reachable.'
            );

            return;
        }

        if ($reescritas > 0) {
            $this->components->task("Rewriting {$reescritas} stored menu labels");
        }
    }

    protected function setInstallationState(string $state): void
    {
        $path = config_path('base-tenant.php');
        $contents = File::get($path);

        $contents = preg_match("/'installation_state'\s*=>/", $contents)
            ? preg_replace("/('installation_state'\s*=>\s*')[^']*(')/", "\${1}{$state}\${2}", $contents)
            : preg_replace('/^(return \[\n)/m', "$1\n    'installation_state' => '{$state}',\n", $contents, 1);

        File::put($path, $contents);

        // El fichero no basta: `kit:install` encadena scaffold y eject en el
        // mismo proceso, y la configuración ya cargada seguiría diciendo
        // `installed`, de modo que el eject se negaría a correr justo después
        // de que el scaffold copiara el código.
        config(['base-tenant.installation_state' => $state]);
    }

    /**
     * @param  array{written: int, skipped: int}  $written
     */
    protected function showSummary(array $written): void
    {
        $this->newLine();
        $this->components->info('Scaffold complete.');

        $this->components->twoColumnDetail('<fg=green>Files written</>', (string) $written['written']);

        if ($written['skipped'] > 0) {
            $this->components->twoColumnDetail('<fg=yellow>Files kept</>', (string) $written['skipped']);
        }

        $this->newLine();
        $this->line('  <options=bold>Next steps</>');
        $this->line('  1. <fg=cyan>composer dump-autoload</>');
        $this->line('  2. Run your test suite against the copied code');
        $this->line('  3. Review the diff, then <fg=cyan>php artisan k2labs-base:eject</>');
        $this->newLine();
        $this->line('  <fg=gray>The package is still installed and still authoritative until you eject.</>');
        $this->line('  <fg=gray>Its service provider and the generated one both run, and the generated</>');
        $this->line('  <fg=gray>one wins for views, translations and Livewire components.</>');
        $this->newLine();
    }
}
