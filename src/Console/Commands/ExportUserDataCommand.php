<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Gdpr\DataExportService;
use Base\Tenant\Jobs\ExportUserData;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * @param  class-string<Model>  $model
     */
    protected function findByKey(string $model, string $needle): ?object
    {
        $instance = new $model;

        // A string key is a uuid here; anything else cannot be one of ours,
        // and asking the database would be an error rather than a no.
        if ($instance->getKeyType() === 'string' && ! Str::isUuid($needle)) {
            return null;
        }

        return $model::query()->whereKey($needle)->first();
    }

    public function handle(DataExportService $exports): int
    {
        if (! Module::enabled(Module::GDPR)) {
            $this->components->warn('The GDPR module is disabled.');

            return self::SUCCESS;
        }

        $model = config('base-tenant.models.user', User::class);
        $needle = (string) $this->argument('user');

        // Una dirección es una dirección y lo demás es una clave: comparar una
        // columna uuid con un correo es un error en PostgreSQL, no una
        // búsqueda sin resultados.
        $user = str_contains($needle, '@')
            ? $model::query()->where('email', $needle)->first()
            : $this->findByKey($model, $needle);

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
