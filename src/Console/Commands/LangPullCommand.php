<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Language;
use Base\Tenant\Languages\LangFileWriter;
use Base\Tenant\Languages\LangSyncerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Bring finished translations down and write them into `lang/`.
 *
 * The written files are a deployable artefact: the point of the webhook that
 * calls this is that a completed language appears without a release, and the
 * point of committing the result is that the next release does not lose it.
 */
class LangPullCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:lang-pull
                            {locale?* : Locales to pull (defaults to every locale the project has)}
                            {--enable : Enable each pulled locale once it has been written}';

    protected $description = 'Download translations from LangSyncer and write them to lang/';

    public function handle(LangSyncerClient $client, LangFileWriter $writer): int
    {
        if (! $client->configured()) {
            $this->components->warn('LangSyncer is not configured; nothing to pull.');

            return self::SUCCESS;
        }

        try {
            $locales = $this->argument('locale') ?: $client->locales();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $reference = (string) config('base-tenant.languages.reference', 'en');
        $total = 0;

        foreach ($locales as $locale) {
            // The reference locale is the source. Overwriting it with what came
            // back from translation would let a round trip quietly rewrite the
            // text everything else is translated from.
            if ($locale === $reference) {
                continue;
            }

            try {
                $translations = $client->pull($locale);
            } catch (Throwable $exception) {
                $this->components->error("{$locale}: {$exception->getMessage()}");

                continue;
            }

            $changed = $writer->write($locale, $translations);
            $total += $changed;

            $this->line(sprintf('  <fg=green>%s</> %d keys written', $locale, $changed));

            if ($this->option('enable') && $changed > 0) {
                try {
                    Language::enable($locale);
                } catch (Throwable $exception) {
                    $this->components->warn("{$locale}: {$exception->getMessage()}");
                }
            }
        }

        // Translations live in compiled views and in the translator's own
        // memory. Without clearing both, the new strings are on disk and
        // nowhere else until something else happens to evict them.
        Artisan::call('view:clear');
        Language::flush();

        $this->components->info("Wrote {$total} keys.");

        return self::SUCCESS;
    }
}
