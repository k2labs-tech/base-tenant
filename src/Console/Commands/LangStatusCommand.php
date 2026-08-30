<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Language;
use Base\Tenant\Languages\TranslationCoverage;
use Illuminate\Console\Command;

/**
 * How complete each locale is against the reference one.
 *
 * Enabling an incomplete language is a legitimate choice: the fallback covers
 * the holes. What is not acceptable is not knowing which holes, which is what
 * this exists to answer.
 */
class LangStatusCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:lang-status
                            {locale? : Only this locale}
                            {--reference= : The locale to compare against}
                            {--missing : List the missing keys}
                            {--fail-under= : Exit non-zero if any locale falls below this percentage}';

    protected $description = 'Report translation coverage per locale against the reference';

    public function handle(TranslationCoverage $coverage): int
    {
        $reference = $this->option('reference') ?? config('base-tenant.languages.reference', 'en');

        $locales = $this->argument('locale')
            ? [$this->argument('locale')]
            : $this->locales();

        if ($locales === []) {
            $this->components->warn('No locales to report on.');

            return self::SUCCESS;
        }

        $threshold = $this->option('fail-under') !== null ? (int) $this->option('fail-under') : null;
        $below = [];

        $rows = [];

        foreach ($locales as $locale) {
            $report = $coverage->for($locale, $reference);

            $rows[] = [
                $locale.($locale === $reference ? ' (reference)' : ''),
                $report['percentage'].'%',
                $report['translated'].'/'.$report['total'],
                count($report['missing']),
            ];

            if ($threshold !== null && $report['percentage'] < $threshold) {
                $below[] = $locale;
            }

            if ($this->option('missing') && $report['missing'] !== []) {
                $this->newLine();
                $this->line("  <fg=yellow>{$locale}</> is missing:");

                foreach ($report['missing'] as $key) {
                    $this->line("    <fg=gray>{$key}</>");
                }
            }
        }

        $this->newLine();
        $this->table(['Locale', 'Coverage', 'Keys', 'Missing'], $rows);

        if ($below !== []) {
            $this->components->error(sprintf(
                'Below %d%%: %s.',
                $threshold,
                implode(', ', $below),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Every locale worth reporting on: the ones registered in the database,
     * and the ones that only exist as a directory.
     *
     * Reading only the database would hide a locale someone has started
     * translating but not registered yet, which is exactly when this report is
     * most useful.
     *
     * @return list<string>
     */
    protected function locales(): array
    {
        $registered = Language::all()->pluck('code')->all();

        $onDisk = [];

        foreach (app(TranslationCoverage::class)->paths() as $path) {
            foreach (glob($path.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
                $onDisk[] = basename($directory);
            }
        }

        $locales = array_values(array_unique([...$registered, ...$onDisk]));

        sort($locales);

        return array_values(array_filter($locales, fn (string $locale): bool => $locale !== 'vendor'));
    }
}
