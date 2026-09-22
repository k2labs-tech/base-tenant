<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Console\Support\CodeTransformer;
use Base\Tenant\Console\Support\DependencyTransferManager;
use Base\Tenant\Console\Support\ScaffoldPlan;
use Base\Tenant\Console\Support\StylesheetManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Removes the package, leaving the application running on the code that
 * `k2labs-base:scaffold` copied into it.
 */
class EjectCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:eject
        {--dry-run : Show what would happen without changing anything}
        {--keep-package : Transfer dependencies but do not run composer remove}
        {--force : Skip the confirmations and overwrite an existing copied theme}';

    protected $description = 'Remove the package and hand its dependencies to your application';

    protected DependencyTransferManager $dependencies;

    public function handle(): int
    {
        $packagePath = dirname(__DIR__, 3);

        $this->dependencies = new DependencyTransferManager($packagePath, base_path());

        if (! $this->verifyState()) {
            return self::FAILURE;
        }

        $missing = $this->missingScaffoldedFiles($packagePath);

        if (! $this->confirmMissingFiles($missing)) {
            return self::FAILURE;
        }

        $this->showPlan();

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Remove the package and transfer its dependencies?', false)) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        $backup = $this->dependencies->backup();

        try {
            $transferred = $this->dependencies->transfer();
            $this->components->task('Transferring dependencies');

            $this->takeStylesheet($packagePath);

            // Last, and only once everything that can throw already has. Set
            // earlier, a failure here would leave the config saying `ejected`
            // with the package still installed, and `verifyState()` would
            // refuse to run again -- unrecoverable without editing config by
            // hand.
            $this->setInstallationState('ejected');
            $this->components->task('Updating installation state');

            // Before the package leaves: once its files are gone, nothing of
            // it that has not already been loaded can be autoloaded, and the
            // rewrite would die halfway through with the command reporting
            // success.
            $rewritten = $this->rewriteLeftoverReferences();

            if (! $this->option('keep-package')) {
                $this->removePackage();
            }
        } catch (\Throwable $exception) {
            $this->dependencies->restore($backup);

            // Belt and braces: `verifyState()` only lets this command run from
            // `scaffolded`, so putting it back is always the right value.
            $this->setInstallationState('scaffolded');

            $this->components->error("Eject failed, composer.json restored: {$exception->getMessage()}");

            return self::FAILURE;
        }

        File::delete($backup);

        $this->showSummary($transferred);
        $this->reportRewrittenReferences($rewritten);

        return self::SUCCESS;
    }

    protected function verifyState(): bool
    {
        $state = config('base-tenant.installation_state');

        if ($state === 'ejected') {
            $this->components->warn('This project has already ejected the package.');

            return false;
        }

        if ($state !== 'scaffolded') {
            $this->components->error('Run `php artisan k2labs-base:scaffold` first: there is no copied code to fall back on.');

            return false;
        }

        return true;
    }

    /**
     * Files the scaffold should have written that are not there. Ejecting with
     * gaps leaves an application that cannot boot.
     *
     * @return array<int, string>
     */
    protected function missingScaffoldedFiles(string $packagePath): array
    {
        $plan = new ScaffoldPlan($packagePath, base_path());

        $missing = array_map(
            static fn (array $file): string => $file['relative'],
            array_filter(
                $plan->files(),
                static fn (array $file): bool => ! File::exists($file['destination'])
            )
        );

        if (! File::exists(app_path('Providers/TenancyServiceProvider.php'))) {
            $missing[] = 'app/Providers/TenancyServiceProvider.php';
        }

        return array_values($missing);
    }

    /**
     * @param  array<int, string>  $missing
     */
    protected function confirmMissingFiles(array $missing): bool
    {
        if ($missing === []) {
            return true;
        }

        $this->newLine();
        $this->components->warn(count($missing).' scaffolded files are missing:');

        foreach (array_slice($missing, 0, 10) as $file) {
            $this->line("  <fg=yellow>{$file}</>");
        }

        if (count($missing) > 10) {
            $this->line('  <fg=gray>… and '.(count($missing) - 10).' more</>');
        }

        $this->newLine();
        $this->line('  <fg=gray>Re-run `php artisan k2labs-base:scaffold` to fill the gaps.</>');
        $this->newLine();

        return $this->option('force') || $this->confirm('Continue anyway?', false);
    }

    protected function showPlan(): void
    {
        $dependencies = $this->dependencies->pendingDependencies();
        $repositories = $this->dependencies->pendingRepositories();
        $files = $this->dependencies->pendingAutoloadFiles();

        $this->newLine();
        $this->components->info('Eject plan');

        if ($dependencies !== []) {
            $this->line('  <options=bold>Dependencies moving to your composer.json</>');

            foreach ($dependencies as $name => $constraint) {
                $this->components->twoColumnDetail("  {$name}", $constraint);
            }
        }

        if ($repositories !== []) {
            $this->newLine();
            $this->line('  <options=bold>Repositories added</>: '.implode(', ', array_keys($repositories)));
        }

        if ($files !== []) {
            $this->newLine();
            $this->line('  <options=bold>Autoloaded files added</>: '.implode(', ', $files));
        }

        $this->newLine();
        $this->line('  <options=bold>Then</>');
        $this->line('  - installation_state → ejected');
        $this->line('  - theme copied to resources/css/base-tenant.css, app.css repointed at it');

        if (! $this->option('keep-package')) {
            $this->line('  - <fg=cyan>composer remove k2labs/base-tenant</>');
        }
    }

    protected function removePackage(): void
    {
        $this->newLine();
        $this->components->info('Running composer remove k2labs/base-tenant …');

        $process = new Process(['composer', 'remove', 'k2labs/base-tenant', '--no-interaction'], base_path());
        $process->setTimeout(600);

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->components->warn('composer remove did not finish cleanly. Run it by hand once you have resolved the problem.');
        }

        $this->forgetPackageDiscovery();
    }

    /**
     * Laravel caches which packages registered which providers, and that cache
     * survives the package it describes.
     *
     * Left behind, it names a provider whose class is gone: the application
     * either dies on boot, or — if the files are still there because the
     * removal did not finish — boots the package alongside the copied code,
     * with both scheduling the same maintenance work.
     */
    protected function forgetPackageDiscovery(): void
    {
        foreach (['packages.php', 'services.php'] as $file) {
            $path = base_path("bootstrap/cache/{$file}");

            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    /**
     * The stylesheet has to come with the application. The scaffolded views use
     * the package's state colour scales (`bg-danger-500`, `text-warning-600`),
     * so the theme is copied next to `app.css` and the import repointed at the
     * copy. Dropping the import instead would leave a build that succeeds and
     * an interface that has silently lost every state colour.
     *
     * The `@source` paths go at the same time: they pointed into the package,
     * and the copied views under `resources/views/` are already covered by the
     * application's own source. Copy and rewrite live in one method because
     * either half alone is broken -- a copy nothing imports, or an import
     * pointing at a file that was never written.
     */
    protected function takeStylesheet(string $packagePath): void
    {
        $path = resource_path('css/app.css');

        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);
        $detached = (new StylesheetManager)->detach($contents);

        if ($detached === $contents) {
            return;
        }

        $this->copyTheme("{$packagePath}/resources/css/base-tenant.css");

        File::put($path, $detached);

        $this->components->task('Repointing resources/css/app.css at the copied theme');
    }

    /**
     * The copied theme is the application's to edit from here on, and the docs
     * say so, so a second eject must not quietly overwrite those edits. The
     * import is still repointed either way: the file it names is there, it is
     * just the application's version rather than ours.
     */
    protected function copyTheme(string $theme): void
    {
        $destination = resource_path('css/base-tenant.css');

        if (File::exists($destination) && ! $this->option('force') && File::get($destination) !== File::get($theme)) {
            $this->components->warn('resources/css/base-tenant.css already exists and differs: keeping yours. Re-run with --force to overwrite it.');

            return;
        }

        // Deliberately unguarded: if the theme is not there the package install
        // is broken, and failing loudly beats writing an import that dangles.
        File::copy($theme, $destination);

        $this->components->task('Copying the theme to resources/css/base-tenant.css');
    }

    protected function setInstallationState(string $state): void
    {
        $path = config_path('base-tenant.php');

        if (! File::exists($path)) {
            return;
        }

        File::put($path, preg_replace(
            "/('installation_state'\s*=>\s*')[^']*(')/",
            "\${1}{$state}\${2}",
            File::get($path)
        ));

        // Igual que en el scaffold: el fichero solo lo lee el siguiente
        // proceso, y aquí el rollback del `catch` tiene que valer ya.
        config(['base-tenant.installation_state' => $state]);
    }

    /**
     * @param  array{require: array<string, string>, repositories: array<string, mixed>, files: array<int, string>}  $transferred
     */
    /**
     * Files of the application's own that still name the package.
     *
     * Eject rewrites what it copies; it cannot rewrite what the application
     * wrote. A seeder, a test or a job that imported `Base\\Tenant\\…` keeps
     * importing a class that no longer exists, and nothing says so until that
     * code runs — `db:seed` on a fresh database, typically, which is when
     * there is nothing to sign in with.
     */
    /**
     * @return array{rewritten: array<int, string>, skipped: array<int, string>}
     */
    protected function rewriteLeftoverReferences(): array
    {
        $transformer = new CodeTransformer;
        $rewritten = [];
        $skipped = [];

        foreach ($this->leftoverReferences() as $relative) {
            $path = base_path($relative);
            $contents = (string) File::get($path);

            // The package's own provider has no copy in the application: the
            // generated one replaces it, and rewriting the name would point
            // the file at a class that was never created.
            if (str_contains($contents, 'BaseTenantServiceProvider')) {
                $skipped[] = $relative;

                continue;
            }

            File::put($path, $transformer->transform($contents));
            $rewritten[] = $relative;
        }

        return ['rewritten' => $rewritten, 'skipped' => $skipped];
    }

    /**
     * @param  array{rewritten: array<int, string>, skipped: array<int, string>}  $result
     */
    protected function reportRewrittenReferences(array $result): void
    {
        ['rewritten' => $rewritten, 'skipped' => $skipped] = $result;

        if ($rewritten !== []) {
            $this->components->info('Rewrote the references your own files made to the package:');

            foreach ($rewritten as $file) {
                $this->line("  <fg=green>{$file}</>");
            }

            $this->newLine();
            $this->line('  <fg=gray>Same rewrite the copied code got. Read the diff before committing.</>');
            $this->newLine();
        }

        if ($skipped !== []) {
            $this->components->warn('These name the package and were left alone:');

            foreach ($skipped as $file) {
                $this->line("  <fg=yellow>{$file}</>");
            }

            $this->newLine();
            $this->line('  <fg=gray>They mention the package service provider, which has no copy here:</>');
            $this->line('  <fg=gray>App\Providers\TenancyServiceProvider replaces it.</>');
            $this->newLine();
        }
    }

    /**
     * @return array<int, string>
     */
    protected function leftoverReferences(): array
    {
        // `config/base-tenant.php` and `bootstrap/providers.php` are rewritten
        // by the scaffold step, which knows what belongs in them.
        $roots = ['app', 'database', 'routes', 'tests'];
        $found = [];

        foreach ($roots as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            foreach (Finder::create()->files()->in($path)->name(['*.php', '*.blade.php'])->sortByName() as $file) {
                $contents = (string) file_get_contents($file->getPathname());

                if (str_contains($contents, 'Base\\Tenant\\') || str_contains($contents, 'base-tenant::')) {
                    $found[] = $root.'/'.str_replace('\\', '/', $file->getRelativePathname());
                }
            }
        }

        return $found;
    }

    protected function showSummary(array $transferred): void
    {
        $this->newLine();
        $this->components->info('Ejected.');

        $this->components->twoColumnDetail('<fg=green>Dependencies transferred</>', (string) count($transferred['require']));

        $this->newLine();
        $this->line('  <options=bold>Next steps</>');
        $this->line('  1. <fg=cyan>composer update</>');
        $this->line('  2. <fg=cyan>composer dump-autoload</>');
        $this->line('  3. <fg=cyan>php artisan optimize:clear</>');
        $this->line('  4. Run your test suite');
        $this->newLine();
        $this->line('  <fg=gray>config/base-tenant.php stays: the copied code reads it. Rename it if</>');
        $this->line('  <fg=gray>you like, updating the config() calls in app/ to match.</>');
        $this->newLine();
    }
}
