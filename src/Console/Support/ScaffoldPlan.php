<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Works out which files move where.
 *
 * Every directory is copied whole and the exceptions are listed explicitly.
 * The inverse -- enumerating what to copy -- is what let earlier versions of
 * this command silently miss new subsystems, so anything added to `src/` from
 * now on is scaffolded by default and has to be opted out of on purpose.
 */
class ScaffoldPlan
{
    /**
     * Files that must never reach the application: the package's own service
     * provider, which is replaced by a generated one, and the installer
     * machinery, which stops being relevant once the package is gone.
     *
     * @var array<int, string>
     */
    public const EXCLUDED_FROM_SRC = [
        'BaseTenantServiceProvider.php',
        'Console/Support',
        'Console/Commands/InstallCommand.php',
        'Console/Commands/ScaffoldCommand.php',
        'Console/Commands/EjectCommand.php',
    ];

    /**
     * Exceptions to the exclusions above: files under an excluded directory
     * that the copied code still needs.
     *
     * `ModuleField` is support for `MakeModuleCommand`, which does travel, so
     * excluding the whole of `Console/Support` left the module generator
     * importing a class that was never copied — a fatal on its first line.
     *
     * @var array<int, string>
     */
    public const INCLUDED_FROM_SRC = [
        'Console/Support/ModuleField.php',
    ];

    public function __construct(
        protected string $packagePath,
        protected string $basePath,
    ) {}

    /**
     * @return array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->groups() as $group) {
            $files = [...$files, ...$this->filesForGroup($group)];
        }

        return $files;
    }

    /**
     * @return array<string, array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>>
     */
    public function filesByGroup(): array
    {
        $grouped = [];

        foreach ($this->files() as $file) {
            $grouped[$file['group']][] = $file;
        }

        return $grouped;
    }

    /**
     * @return array<int, array{label: string, source: string, destination: string, exclude: array<int, string>, extensions: array<int, string>, transform: bool}>
     */
    public function groups(): array
    {
        return [
            [
                'label' => 'Application code',
                'source' => 'src',
                'destination' => 'app',
                'exclude' => self::EXCLUDED_FROM_SRC,
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Migrations',
                'source' => 'database/migrations',
                'destination' => 'database/migrations',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Factories',
                'source' => 'database/factories',
                'destination' => 'database/factories',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Seeders',
                'source' => 'database/seeders',
                'destination' => 'database/seeders',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Routes',
                'source' => 'routes',
                'destination' => 'routes/tenant',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Blade components',
                'source' => 'resources/views/components',
                'destination' => 'resources/views/components/tenant',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                'label' => 'Views',
                'source' => 'resources/views',
                'destination' => 'resources/views/tenant',
                'exclude' => ['components'],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                // `lang/vendor/{namespace}` is where Laravel keeps namespaced
                // translations. Under `lang/{namespace}` they work, but every
                // tool that reads `lang/` to list the locales — including this
                // package's own `lang:status` — counts the namespace as a
                // language of its own.
                'label' => 'Translations',
                'source' => 'resources/lang',
                'destination' => 'lang/vendor/tenant',
                'exclude' => ['vendor'],
                'extensions' => ['php'],
                'transform' => true,
            ],
            [
                // Laravel has no namespaced JSON translations: a file has to sit
                // at lang/{locale}.json to be loaded at all. These are merged
                // into whatever the application already has rather than copied
                // over it.
                'label' => 'JSON translations',
                'source' => 'resources/lang',
                'destination' => 'lang',
                'exclude' => ['vendor'],
                'extensions' => ['json'],
                'transform' => false,
            ],
            [
                // `MakeModuleCommand` reads these. Once the package is gone
                // there is no vendor directory to fall back to, so they have
                // to arrive with it — rewritten, or every module it generates
                // would import namespaces that no longer exist.
                'label' => 'Module stubs',
                'source' => 'stubs/module',
                'destination' => 'stubs/base-tenant/module',
                'exclude' => [],
                'extensions' => ['stub'],
                'transform' => true,
            ],
            [
                'label' => 'Test kit',
                'source' => 'tests/TenancyAssertions.php',
                'destination' => 'tests/TenancyAssertions.php',
                'exclude' => [],
                'extensions' => ['php'],
                'transform' => true,
            ],
        ];
    }

    /**
     * @param  array{label: string, source: string, destination: string, exclude: array<int, string>, extensions: array<int, string>, transform: bool}  $group
     * @return array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>
     */
    protected function filesForGroup(array $group): array
    {
        $source = "{$this->packagePath}/{$group['source']}";

        if (is_file($source)) {
            return [[
                'source' => $source,
                'destination' => "{$this->basePath}/{$group['destination']}",
                'relative' => $group['destination'],
                'transform' => $group['transform'],
                'group' => $group['label'],
            ]];
        }

        if (! is_dir($source)) {
            return [];
        }

        $finder = Finder::create()->files()->in($source)->sortByName();

        foreach ($group['extensions'] as $extension) {
            $finder->name("*.{$extension}");
        }

        $files = [];

        foreach ($finder as $file) {
            $relative = $this->relativePath($file);

            if ($this->isExcluded($relative, $group['exclude'])
                && ! in_array($relative, self::INCLUDED_FROM_SRC, true)) {
                continue;
            }

            $destination = "{$group['destination']}/{$relative}";

            $files[] = [
                'source' => $file->getPathname(),
                'destination' => "{$this->basePath}/{$destination}",
                'relative' => $destination,
                'transform' => $group['transform'],
                'group' => $group['label'],
            ];
        }

        return $files;
    }

    protected function relativePath(SplFileInfo $file): string
    {
        $relative = $file->getRelativePathname();

        return str_replace('\\', '/', $relative);
    }

    /**
     * @param  array<int, string>  $exclusions
     */
    protected function isExcluded(string $relative, array $exclusions): bool
    {
        foreach ($exclusions as $exclusion) {
            if ($relative === $exclusion || str_starts_with($relative, rtrim($exclusion, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
