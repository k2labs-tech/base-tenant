<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

/**
 * Fail when a model holds account data outside the isolation boundary.
 *
 * A model whose table has `account_id` but which does not use
 * BelongsToAccount is queried with no account scope at all, and nothing at
 * runtime says so. This is the check that does, meant for CI: it exits 1 on
 * any finding.
 *
 * It also flags the opposite mistake -- the trait on a table with no account
 * column -- which fails on the first query anyway, but better here than in
 * production.
 */
class TenancyAuditCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:tenancy-audit
                            {--path=* : Directory to scan (defaults to base-tenant.tenancy.audit.paths)}';

    protected $description = 'Fail when a model with an account_id column does not use BelongsToAccount';

    public function handle(): int
    {
        $paths = $this->paths();
        $findings = [];
        $scanned = 0;
        $scoped = 0;

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                $this->components->warn(__('base-tenant::tenancy.audit.missing_path', ['path' => $path]));

                continue;
            }

            foreach ($this->modelClasses($path) as $class) {
                if ($this->isExempt($class)) {
                    continue;
                }

                $scanned++;

                /** @var Model $model */
                $model = new $class;
                $usesTrait = in_array(BelongsToAccount::class, class_uses_recursive($class), true);
                $column = $usesTrait ? $model->getAccountIdColumn() : 'account_id';
                $table = $model->getTable();

                if (! $this->tableExists($table)) {
                    continue;
                }

                $hasColumn = $this->tableHasColumn($table, $column);

                if ($hasColumn && ! $usesTrait) {
                    $findings[] = [$class, $table, __('base-tenant::tenancy.audit.missing_trait')];

                    continue;
                }

                if ($usesTrait && ! $hasColumn) {
                    $findings[] = [$class, $table, __('base-tenant::tenancy.audit.missing_column', ['column' => $column])];

                    continue;
                }

                if ($usesTrait) {
                    $scoped++;
                }
            }
        }

        if ($findings === []) {
            $this->components->info(__('base-tenant::tenancy.audit.passed', [
                'scanned' => $scanned,
                'scoped' => $scoped,
            ]));

            return self::SUCCESS;
        }

        $this->components->error(__('base-tenant::tenancy.audit.failed', ['count' => count($findings)]));

        $this->table([
            __('base-tenant::tenancy.audit.model'),
            __('base-tenant::tenancy.audit.table'),
            __('base-tenant::tenancy.audit.finding'),
        ], $findings);

        return self::FAILURE;
    }

    /** @return array<int, string> */
    protected function paths(): array
    {
        $given = array_filter((array) $this->option('path'), fn (mixed $path): bool => is_string($path) && $path !== '');

        if ($given !== []) {
            return array_values($given);
        }

        return array_values((array) config('base-tenant.tenancy.audit.paths', [app_path('Models')]));
    }

    /**
     * Concrete Eloquent models declared in the directory, read from each
     * file's own namespace so no directory-to-namespace mapping is assumed.
     *
     * @return array<int, class-string<Model>>
     */
    protected function modelClasses(string $path): array
    {
        $classes = [];

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classDeclaredIn($file->getContents());

            if ($class === null || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    protected function classDeclaredIn(string $contents): ?string
    {
        if (! preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $contents, $class)) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $contents, $match) ? $match[1].'\\' : '';

        return $namespace.$class[1];
    }

    /**
     * Classes, or namespace prefixes ending in a backslash, listed in
     * `base-tenant.tenancy.audit.exempt`.
     */
    protected function isExempt(string $class): bool
    {
        foreach ((array) config('base-tenant.tenancy.audit.exempt', []) as $exempt) {
            if (! is_string($exempt) || $exempt === '') {
                continue;
            }

            if (str_ends_with($exempt, '\\') ? str_starts_with($class, $exempt) : ltrim($exempt, '\\') === $class) {
                return true;
            }
        }

        return false;
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
