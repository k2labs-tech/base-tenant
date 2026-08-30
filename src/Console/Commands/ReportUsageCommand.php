<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\UsageEvent;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Send unreported usage to Stripe Billing Meters.
 *
 * Only metrics that declare a `stripe_meter` are reported; everything else is
 * measured for the product's own purposes and never leaves the database.
 *
 * The batch is claimed before it is sent and released if the send fails, so a
 * run that dies half way repeats nothing and loses nothing. Stripe is given an
 * identifier derived from the batch as well, which makes a retry of the same
 * batch a no-op on their side rather than a second charge.
 */
class ReportUsageCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:report-usage
                            {--chunk=500 : Events per batch}
                            {--dry-run : Show what would be sent without sending it}';

    protected $description = 'Report metered usage to Stripe (idempotent deltas)';

    public function handle(MetricRegistry $registry): int
    {
        if (! Module::enabled(Module::METERING)) {
            $this->components->warn('Metering is disabled; nothing to report.');

            return self::SUCCESS;
        }

        $reportable = collect($registry->all())
            ->filter(fn ($metric): bool => $metric->stripeMeter !== null);

        if ($reportable->isEmpty()) {
            $this->components->info('No metric declares a Stripe meter; nothing to report.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $reported = 0;
        $failed = 0;

        UsageEvent::query()
            ->whereNull('reported_at')
            ->whereIn('metric', $reportable->keys())
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function (Collection $events) use ($registry, $dryRun, &$reported, &$failed): void {
                foreach ($events->groupBy(['account_id', 'metric']) as $accountId => $byMetric) {
                    foreach ($byMetric as $metric => $group) {
                        $delta = (int) $group->sum('delta');

                        // A batch that nets to zero -- an increment and its
                        // refund -- is still marked as reported: there is
                        // nothing to tell Stripe, and leaving it unreported
                        // would make it a permanent resident of every run.
                        if ($dryRun) {
                            $this->line(sprintf(
                                '  <fg=gray>%s</> %s <fg=cyan>%+d</> (%d events)',
                                $accountId,
                                $metric,
                                $delta,
                                $group->count(),
                            ));

                            $reported++;

                            continue;
                        }

                        $this->send($registry, (string) $accountId, (string) $metric, $delta, $group)
                            ? $reported++
                            : $failed++;
                    }
                }
            });

        $this->components->info($dryRun
            ? "Would report {$reported} batches."
            : "Reported {$reported} batches.".($failed > 0 ? " {$failed} failed and will be retried." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Collection<int, UsageEvent>  $events
     */
    protected function send(MetricRegistry $registry, string $accountId, string $metric, int $delta, Collection $events): bool
    {
        $ids = $events->pluck('id')->sort()->values()->all();

        // Claim first. If the send then fails the claim is released, so the
        // only way an event is left stamped is if Stripe accepted it.
        DB::transaction(fn () => UsageEvent::whereIn('id', $ids)->update(['reported_at' => now()]));

        if ($delta === 0) {
            return true;
        }

        try {
            $customer = $this->customerFor($accountId);

            if ($customer === null) {
                // Not an error: an account with no Stripe customer is not
                // being billed, and its usage has nowhere to go.
                return true;
            }

            Cashier::stripe()->billing->meterEvents->create([
                'event_name' => $registry->get($metric)->stripeMeter,
                'identifier' => 'bt_'.sha1($accountId.'|'.$metric.'|'.implode(',', $ids)),
                'payload' => [
                    'stripe_customer_id' => $customer,
                    'value' => (string) $delta,
                ],
            ]);

            return true;
        } catch (Throwable $exception) {
            UsageEvent::whereIn('id', $ids)->update(['reported_at' => null]);

            $this->components->error(sprintf(
                '%s / %s: %s',
                $accountId,
                $metric,
                $exception->getMessage(),
            ));

            return false;
        }
    }

    protected function customerFor(string $accountId): ?string
    {
        $model = config('base-tenant.models.account');

        return $model::query()->whereKey($accountId)->value('stripe_id');
    }
}
