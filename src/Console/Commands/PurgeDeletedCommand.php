<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\File;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;

/**
 * Destroy what was soft-deleted long enough ago.
 *
 * Soft deletion is a grace period, not a filing system. A record that is still
 * there a year after somebody asked for it to go is a record the product
 * promised to delete and did not.
 */
class PurgeDeletedCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:purge-deleted
                            {--days= : Grace period in days (defaults to config)}
                            {--dry-run : Report what would be destroyed}';

    protected $description = 'Hard-delete expired soft-deleted records and anonymise their activity';

    public function handle(FileStore $files): int
    {
        if (! Module::enabled(Module::GDPR)) {
            $this->components->warn('The GDPR module is disabled.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? config('base-tenant.gdpr.retention_days', 30));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $model = config('base-tenant.models.user', User::class);

        $users = $model::onlyTrashed()->where('deleted_at', '<=', $cutoff)->get();

        $this->line(sprintf('  %s %d users deleted before %s',
            $dryRun ? 'Would purge' : 'Purging',
            $users->count(),
            $cutoff->toDateString(),
        ));

        foreach ($users as $user) {
            if ($dryRun) {
                continue;
            }

            // The activity stays; the person is removed from it. An audit
            // trail with the description intact is still an audit trail, and
            // deleting it would destroy the record of what was done to other
            // people's data.
            ActivityLog::query()
                ->acrossAccounts()
                ->where('causer_id', $user->getKey())
                ->update(['causer_id' => null, 'causer_type' => null]);

            $this->purgeFilesOf($user, $files);

            $user->forceDelete();
        }

        $expiredFiles = File::onlyTrashed()->acrossAccounts()->where('deleted_at', '<=', $cutoff)->get();

        $this->line(sprintf('  %s %d files deleted before %s',
            $dryRun ? 'Would purge' : 'Purging',
            $expiredFiles->count(),
            $cutoff->toDateString(),
        ));

        if (! $dryRun) {
            foreach ($expiredFiles as $file) {
                // The bytes first, then the row: the other order leaves an
                // object on the disk that nothing points at and nobody bills.
                $file->storage()->deleteDirectory($file->directory());
                $file->forceDelete();
            }
        }

        $this->components->info($dryRun ? 'Nothing was destroyed.' : 'Purge complete.');

        return self::SUCCESS;
    }

    protected function purgeFilesOf(object $user, FileStore $files): void
    {
        if (! Module::enabled(Module::FILES)) {
            return;
        }

        File::query()
            ->acrossAccounts()
            ->withTrashed()
            ->where('uploaded_by', $user->getKey())
            ->each(function (File $file): void {
                $file->storage()->deleteDirectory($file->directory());
                $file->forceDelete();
            });
    }
}
