<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copy the agent documentation into the host application and point its root
 * stub at it.
 *
 * The package section of `CLAUDE.md` / `AGENTS.md` sits between markers, so
 * re-running this after a `composer update` refreshes the package's half and
 * leaves whatever the project wrote around it exactly as it was.
 */
class PublishAgentDocsCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:publish-agent-docs
                            {--stub=* : Which root stubs to write (default: CLAUDE.md and AGENTS.md)}
                            {--force : Overwrite documentation files that differ}';

    protected $description = 'Publish the agent documentation and root stub into the application';

    public const BEGIN = '<!-- base-tenant:agent-docs:begin -->';

    public const END = '<!-- base-tenant:agent-docs:end -->';

    public function handle(): int
    {
        $source = dirname(__DIR__, 3).'/docs/agents';

        if (! File::isDirectory($source)) {
            $this->components->error('The package has no docs/agents directory.');

            return self::FAILURE;
        }

        $target = base_path('docs/agents/base');

        $written = $this->copyDocs($source, $target);

        $this->components->info("Published {$written} documentation files to docs/agents/base.");

        foreach ($this->stubs() as $stub) {
            $this->writeStub(base_path($stub));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function stubs(): array
    {
        $requested = (array) $this->option('stub');

        return $requested !== [] ? $requested : ['CLAUDE.md', 'AGENTS.md'];
    }

    protected function copyDocs(string $source, string $target): int
    {
        File::ensureDirectoryExists($target);

        $written = 0;

        foreach (File::files($source) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $destination = $target.'/'.$file->getFilename();
            $contents = File::get($file->getPathname());

            // A file the project has edited is not silently replaced: the
            // whole point of publishing is that the copy can be adjusted.
            if (File::exists($destination) && File::get($destination) !== $contents && ! $this->option('force')) {
                $this->components->warn("Kept local changes in docs/agents/base/{$file->getFilename()} (use --force to overwrite).");

                continue;
            }

            File::put($destination, $contents);

            $written++;
        }

        return $written;
    }

    /**
     * Write the package's section of the root stub, leaving everything outside
     * the markers untouched.
     */
    protected function writeStub(string $path): void
    {
        $section = self::BEGIN."\n".$this->section()."\n".self::END;

        if (! File::exists($path)) {
            File::put($path, $section."\n");

            $this->components->info('Created '.basename($path).'.');

            return;
        }

        $contents = File::get($path);

        if (str_contains($contents, self::BEGIN) && str_contains($contents, self::END)) {
            $updated = preg_replace(
                '/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'/s',
                // The replacement goes through a callback so a `$` or a
                // backslash in the documentation is not read as a
                // backreference and silently mangled.
                str_replace('\\', '\\\\', $section),
                $contents,
            );

            File::put($path, (string) $updated);

            $this->components->info('Updated the package section of '.basename($path).'.');

            return;
        }

        File::put($path, rtrim($contents)."\n\n".$section."\n");

        $this->components->info('Added the package section to '.basename($path).'.');
    }

    protected function section(): string
    {
        return <<<'MARKDOWN'
        ## k2labs/base-tenant

        This application is built on the `k2labs/base-tenant` package. Its capabilities are
        documented in `docs/agents/base/`.

        **Before implementing anything, read `docs/agents/base/00-index.md` and check
        the capability map.** Most of what a multi-tenant SaaS needs already exists in
        the package: tenancy, permissions, menus, settings, activity, plan features,
        usage metering, file storage, languages and social login. Reimplementing one of
        them produces code that looks right, passes its own tests, and quietly breaks
        tenant isolation, plan limits or the audit trail.

        Non-negotiables:

        - Every user-facing string goes through `__()`, in both `en` and `es`.
        - A tenant-owned model uses the `BelongsToAccount` trait.
        - Never trust the client about size, type or ownership; re-read it server-side.
        - Destructive confirmations use `flux:modal` and name the object.
        - A guard needs a test that fails when the guard is removed.

        Package commands live under `k2labs-base:`.

        This section is maintained by `php artisan k2labs-base:publish-agent-docs`.
        Anything you write outside the markers is preserved.
        MARKDOWN;
    }
}
