<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Domain;
use Base\Tenant\Models\AccountDomain;
use Base\Tenant\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Re-check the domains customers have pointed here.
 *
 * A domain is not verified once and trusted forever: zones get edited, records
 * get deleted, and a hostname that stopped proving ownership should stop being
 * treated as proof. It runs daily.
 */
class VerifyDomainsCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:verify-domains
                            {--domain= : Check only this hostname}
                            {--all : Check every domain, not only the ones due}';

    protected $description = 'Re-check DNS verification for customer domains';

    public function handle(): int
    {
        Module::ensure(Module::DOMAINS);

        $domains = $this->targets();

        if ($domains->isEmpty()) {
            $this->components->info(__('base-tenant::domains.console.nothing_due'));

            return self::SUCCESS;
        }

        $verified = 0;
        $failed = 0;

        foreach ($domains as $domain) {
            $before = $domain->status;

            if (Domain::verify($domain)) {
                $verified++;

                if ($before !== AccountDomain::STATUS_VERIFIED) {
                    $this->components->info(__('base-tenant::domains.console.now_verified', [
                        'hostname' => $domain->hostname,
                    ]));
                }

                continue;
            }

            $failed++;

            $this->components->warn(__('base-tenant::domains.console.not_verified', [
                'hostname' => $domain->hostname,
            ]));
        }

        $this->components->info(__('base-tenant::domains.console.summary', [
            'verified' => $verified,
            'failed' => $failed,
        ]));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, AccountDomain>
     */
    protected function targets(): Collection
    {
        if ($hostname = $this->option('domain')) {
            return AccountDomain::query()
                ->where('hostname', Domain::normalizeHostname($hostname))
                ->get();
        }

        if ($this->option('all')) {
            return AccountDomain::query()->orderBy('hostname')->get();
        }

        return Domain::dueForVerification();
    }
}
