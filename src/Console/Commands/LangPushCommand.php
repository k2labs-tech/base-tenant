<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Languages\LangFileWriter;
use Base\Tenant\Languages\LangSyncerClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Send the reference locale's keys to LangSyncer.
 *
 * Only the reference locale goes up: it is the source text, and pushing a
 * half-finished translation as if it were source would ask translators to work
 * from a translation.
 */
class LangPushCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:lang-push
                            {--locale= : The source locale (defaults to the reference)}
                            {--dry-run : Show how many keys would be sent}';

    protected $description = 'Send new and changed keys to LangSyncer';

    public function handle(LangSyncerClient $client, LangFileWriter $writer): int
    {
        if (! $client->configured()) {
            $this->components->warn('LangSyncer is not configured; nothing to push.');

            return self::SUCCESS;
        }

        $locale = $this->option('locale') ?: config('base-tenant.languages.reference', 'en');

        $keys = $writer->read($locale);

        if ($keys === []) {
            $this->components->warn("No keys found for `{$locale}`.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('Would send %d keys from `%s`.', count($keys), $locale));

            return self::SUCCESS;
        }

        try {
            $result = $client->push($keys);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Sent %d keys. Created %s, updated %s.',
            count($keys),
            $result['created'] ?? '?',
            $result['updated'] ?? '?',
        ));

        return self::SUCCESS;
    }
}
