<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Models\EmailSuppression;
use Base\Tenant\Support\Module;
use Base\Tenant\Suppressions\SuppressionManager;
use Base\Tenant\Transfer\Csv;
use Illuminate\Console\Command;

/**
 * Load a suppression list from a CSV.
 *
 * For the first day on a new installation: whatever the previous provider had
 * suppressed has to come across, or the reputation that was earned is thrown
 * away on the first send.
 */
class ImportSuppressionsCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:import-suppressions
                            {file : A CSV with an email column}
                            {--column=email : Which column holds the address}
                            {--reason=bounce : Reason to record when the file does not say}';

    protected $description = 'Load suppressed addresses from a CSV';

    public function handle(SuppressionManager $suppressions): int
    {
        if (! Module::enabled(Module::SUPPRESSIONS)) {
            $this->components->warn('Suppressions are disabled.');

            return self::SUCCESS;
        }

        $file = (string) $this->argument('file');

        if (! is_readable($file)) {
            $this->components->error("Cannot read `{$file}`.");

            return self::FAILURE;
        }

        $column = (string) $this->option('column');
        $imported = 0;
        $skipped = 0;

        foreach (Csv::rows($file) as $row) {
            $email = trim((string) ($row[$column] ?? ''));

            // A file exported by hand is full of blanks and half-addresses.
            // Counting them is more useful than failing on the first one.
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                continue;
            }

            $suppressions->suppress(
                $email,
                $this->reasonFor($row) ?? (string) $this->option('reason'),
                'import',
            );

            $imported++;
        }

        $this->components->info("Suppressed {$imported} addresses.".($skipped > 0 ? " Skipped {$skipped} unusable rows." : ''));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function reasonFor(array $row): ?string
    {
        $reason = mb_strtolower(trim((string) ($row['reason'] ?? '')));

        return in_array($reason, [
            EmailSuppression::BOUNCE,
            EmailSuppression::COMPLAINT,
            EmailSuppression::MANUAL,
            EmailSuppression::UNSUBSCRIBE,
        ], true) ? $reason : null;
    }
}
