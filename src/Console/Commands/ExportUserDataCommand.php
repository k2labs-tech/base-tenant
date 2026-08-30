<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Gdpr\DataExportService;
use Base\Tenant\Jobs\ExportUserData;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;

/**
 * Export one person's data, for a request that arrives by email rather than
 * through the interface.
 */
class ExportUserDataCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:export-user-data
                            {user : Id or email address}
                            {--path= : Write the archive here instead of notifying the user}';

    protected $description = 'Export everything held about one person';

    public function handle(DataExportService $exports): int
    {
        if (! Module::enabled(Module::GDPR)) {
            $this->components->warn('The GDPR module is disabled.');

            return self::SUCCESS;
        }

        $model = config('base-tenant.models.user', User::class);
        $needle = (string) $this->argument('user');

        $user = $model::query()->where('id', $needle)->orWhere('email', $needle)->first();

        if (! $user) {
            $this->components->error("No user matched `{$needle}`.");

            return self::FAILURE;
        }

        // Writing to a path is the path a lawyer asks for: the file, now, on
        // this machine, without a mail round trip.
        if ($path = $this->option('path')) {
            $exports->build($user, $path);

            $this->components->info("Wrote {$path}.");

            return self::SUCCESS;
        }

        ExportUserData::dispatch($user->getKey());

        $this->components->info("Queued an export for {$user->email}; they will be emailed a link.");

        return self::SUCCESS;
    }
}
