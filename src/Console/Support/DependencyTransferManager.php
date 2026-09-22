<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Moves what the package depends on into the application's composer.json, so
 * removing the package does not take Cashier, Sanctum, Flux or spatie with it.
 *
 * Requirements are read from the package manifest rather than listed here, so
 * a dependency added upstream is transferred without anyone remembering to
 * update this class.
 */
class DependencyTransferManager
{
    /**
     * Requirements the application already has by virtue of being a Laravel
     * application, or that only matter while developing the package itself.
     *
     * @var array<int, string>
     */
    protected const SKIPPED = [
        'php',
        'illuminate/contracts',
        'illuminate/database',
        'illuminate/support',
    ];

    public function __construct(
        protected string $packagePath,
        protected string $basePath,
    ) {}

    /**
     * @return array<string, string>
     */
    public function pendingDependencies(): array
    {
        $packageRequires = $this->manifest("{$this->packagePath}/composer.json")['require'] ?? [];
        $appManifest = $this->manifest("{$this->basePath}/composer.json");
        $appRequires = $appManifest['require'] ?? [];

        $pending = [];

        foreach ($packageRequires as $name => $constraint) {
            if (in_array($name, self::SKIPPED, true) || isset($appRequires[$name])) {
                continue;
            }

            $pending[$name] = $constraint;
        }

        return $pending;
    }

    /**
     * Private repositories the package declares, such as the Flux Pro
     * registry, which the application needs to resolve those requirements.
     *
     * @return array<string, mixed>
     */
    public function pendingRepositories(): array
    {
        $packageRepositories = $this->manifest("{$this->packagePath}/composer.json")['repositories'] ?? [];
        $appRepositories = $this->manifest("{$this->basePath}/composer.json")['repositories'] ?? [];

        // Composer accepts two shapes — a map of names and a plain list — and
        // an application may use either, so the keys of one side say nothing
        // about the other. What identifies a repository is its url.
        $known = [];

        foreach ($appRepositories as $repository) {
            if (is_array($repository) && isset($repository['url'])) {
                $known[] = $repository['url'];
            }
        }

        $pending = [];

        foreach ($packageRepositories as $name => $repository) {
            if (is_array($repository) && isset($repository['url']) && in_array($repository['url'], $known, true)) {
                continue;
            }

            $pending[$name] = $repository;
        }

        return $pending;
    }

    /**
     * Add the package's repositories without changing the shape the
     * application wrote.
     *
     * Spreading a map into a list renumbers its keys, and the result — an
     * object with a "0" in it — is neither shape. Composer refuses to read it
     * at all, which takes the application's own `composer remove`, `require`
     * and `update` with it.
     *
     * @param  array<array-key, mixed>  $existing
     * @param  array<array-key, mixed>  $pending
     * @return array<array-key, mixed>
     */
    protected function mergeRepositories(array $existing, array $pending): array
    {
        if ($existing !== [] && array_is_list($existing)) {
            foreach ($pending as $repository) {
                $existing[] = $repository;
            }

            return $existing;
        }

        return [...$existing, ...$pending];
    }

    /**
     * The package autoloads a helpers file; once the code is in the
     * application, the application has to.
     */
    public function pendingAutoloadFiles(): array
    {
        $helpers = 'app/helpers.php';

        if (! File::exists("{$this->basePath}/{$helpers}")) {
            return [];
        }

        $existing = $this->manifest("{$this->basePath}/composer.json")['autoload']['files'] ?? [];

        return in_array($helpers, $existing, true) ? [] : [$helpers];
    }

    /**
     * Writes the pending changes and returns what was added.
     *
     * @return array{require: array<string, string>, repositories: array<string, mixed>, files: array<int, string>}
     */
    public function transfer(): array
    {
        $path = "{$this->basePath}/composer.json";
        $manifest = $this->manifest($path);

        $dependencies = $this->pendingDependencies();
        $repositories = $this->pendingRepositories();
        $files = $this->pendingAutoloadFiles();

        $manifest['require'] = [...$manifest['require'] ?? [], ...$dependencies];
        ksort($manifest['require']);

        if ($repositories !== []) {
            $manifest['repositories'] = $this->mergeRepositories(
                $manifest['repositories'] ?? [],
                $repositories
            );
        }

        if ($files !== []) {
            $manifest['autoload']['files'] = [...$manifest['autoload']['files'] ?? [], ...$files];
        }

        File::put($path, json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )."\n");

        return ['require' => $dependencies, 'repositories' => $repositories, 'files' => $files];
    }

    public function backup(): string
    {
        $path = "{$this->basePath}/composer.json";
        $backup = "{$path}.base-tenant-backup";

        File::copy($path, $backup);

        return $backup;
    }

    public function restore(string $backup): void
    {
        if (File::exists($backup)) {
            File::move($backup, "{$this->basePath}/composer.json");
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException("composer.json not found at {$path}");
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("composer.json at {$path} is not valid JSON");
        }

        return $decoded;
    }
}
