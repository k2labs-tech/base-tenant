<?php

declare(strict_types=1);

namespace Base\Tenant\Domains;

use Base\Tenant\Exceptions\DomainException;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountDomain;
use Base\Tenant\Services\ActivityLogService;
use Base\Tenant\Support\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where a customer's product lives: a subdomain of the central domain, and any
 * domains of their own they have proved they control.
 *
 * The two halves are here together because they answer the same question --
 * "what host serves this account?" -- and because the rules that keep them
 * safe are the same rules: a name is claimed once, cannot be a reserved word,
 * and cannot shadow the product's own front door.
 */
class DomainManager
{
    public function __construct(protected DomainVerifier $verifier) {}

    // -----------------------------------------------------------------
    // Subdomains
    // -----------------------------------------------------------------

    /**
     * Reserved names, lower-cased.
     *
     * These are not a style preference. `www` and `mail` collide with records
     * the installation already publishes; `api` and `admin` collide with
     * routes the product itself may serve; the rest are the names an attacker
     * picks when they want a link to look official.
     *
     * @return list<string>
     */
    public function reservedSubdomains(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $name): string => mb_strtolower($name),
            config('base-tenant.domains.subdomains.reserved', [])
        )));
    }

    public function normalizeSubdomain(string $subdomain): string
    {
        return mb_strtolower(trim($subdomain));
    }

    /**
     * Syntactically valid as a DNS label: letters, digits and inner hyphens.
     *
     * A leading or trailing hyphen is rejected because it is not a legal
     * label, and a name made only of digits because some resolvers treat it as
     * an address.
     */
    public function isSubdomainWellFormed(string $subdomain): bool
    {
        $subdomain = $this->normalizeSubdomain($subdomain);

        $min = (int) config('base-tenant.domains.subdomains.min_length', 3);
        $max = (int) config('base-tenant.domains.subdomains.max_length', 63);

        if (mb_strlen($subdomain) < $min || mb_strlen($subdomain) > $max) {
            return false;
        }

        if (ctype_digit($subdomain)) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $subdomain);
    }

    public function isSubdomainReserved(string $subdomain): bool
    {
        return in_array($this->normalizeSubdomain($subdomain), $this->reservedSubdomains(), true);
    }

    /**
     * Free to claim: well formed, not reserved, and held by nobody else.
     *
     * `$except` is the account asking, so re-saving its own subdomain is not
     * reported as a collision with itself.
     */
    public function isSubdomainAvailable(string $subdomain, ?Account $except = null): bool
    {
        $subdomain = $this->normalizeSubdomain($subdomain);

        if (! $this->isSubdomainWellFormed($subdomain) || $this->isSubdomainReserved($subdomain)) {
            return false;
        }

        return ! $this->accounts()
            ->where('subdomain', $subdomain)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
    }

    /**
     * @throws DomainException
     */
    public function claimSubdomain(Account $account, string $subdomain): void
    {
        if (! $this->subdomainsEnabled()) {
            throw DomainException::subdomainsDisabled();
        }

        $subdomain = $this->normalizeSubdomain($subdomain);

        if (! $this->isSubdomainWellFormed($subdomain)) {
            throw DomainException::subdomainInvalid();
        }

        if ($this->isSubdomainReserved($subdomain)) {
            throw DomainException::subdomainReserved($subdomain);
        }

        $previous = $account->subdomain;

        if ($previous === $subdomain) {
            return;
        }

        // The unique index is the real guard against two accounts claiming the
        // same name in the same instant; the check above only produces a
        // readable message in the ordinary case.
        try {
            $account->forceFill(['subdomain' => $subdomain])->save();
        } catch (UniqueConstraintViolationException) {
            throw DomainException::subdomainTaken($subdomain);
        }

        ActivityLogService::log(
            subject: $account,
            action: 'subdomain.changed',
            oldValues: ['subdomain' => $previous],
            newValues: ['subdomain' => $subdomain],
        );
    }

    public function releaseSubdomain(Account $account): void
    {
        $previous = $account->subdomain;

        if ($previous === null) {
            return;
        }

        $account->forceFill(['subdomain' => null])->save();

        ActivityLogService::log(
            subject: $account,
            action: 'subdomain.released',
            oldValues: ['subdomain' => $previous],
        );
    }

    /**
     * The host that serves this account: its primary verified domain if it has
     * one, otherwise its subdomain, otherwise the central domain.
     */
    public function hostFor(Account $account): ?string
    {
        $primary = $this->domainsFor($account)
            ->firstWhere(fn (AccountDomain $domain): bool => $domain->isVerified() && $domain->is_primary);

        if ($primary) {
            return $primary->hostname;
        }

        $central = $this->centralDomain();

        if ($account->subdomain && $central) {
            return $account->subdomain.'.'.$central;
        }

        return $central;
    }

    public function urlFor(Account $account): ?string
    {
        $host = $this->hostFor($account);

        return $host ? rtrim(config('base-tenant.domains.scheme', 'https').'://'.$host, '/') : null;
    }

    // -----------------------------------------------------------------
    // Custom domains
    // -----------------------------------------------------------------

    /**
     * @return Collection<int, AccountDomain>
     */
    public function domainsFor(Account $account): Collection
    {
        return AccountDomain::query()
            ->where('account_id', $account->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('hostname')
            ->get();
    }

    public function normalizeHostname(string $hostname): string
    {
        $hostname = mb_strtolower(trim($hostname));

        // People paste a URL when asked for a domain. Take the host out of it
        // rather than refusing, which is the answer they meant.
        if (str_contains($hostname, '://')) {
            $hostname = (string) parse_url($hostname, PHP_URL_HOST);
        }

        return rtrim(ltrim($hostname, '.'), './');
    }

    public function isHostnameWellFormed(string $hostname): bool
    {
        $hostname = $this->normalizeHostname($hostname);

        if ($hostname === '' || mb_strlen($hostname) > 253 || ! str_contains($hostname, '.')) {
            return false;
        }

        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
            $hostname
        );
    }

    /**
     * Register a domain for an account. It starts unverified and stays that
     * way until the customer publishes the TXT record.
     *
     * @throws DomainException
     */
    public function addDomain(Account $account, string $hostname): AccountDomain
    {
        // Checked here and not only in the screen: the screen hides the form,
        // but a Livewire action or a command reaches this method directly.
        if (! $this->customDomainsEnabled()) {
            throw DomainException::customDomainsDisabled();
        }

        $hostname = $this->normalizeHostname($hostname);

        if (! $this->isHostnameWellFormed($hostname)) {
            throw DomainException::hostnameInvalid($hostname);
        }

        foreach (config('base-tenant.tenancy.central_domains', []) as $central) {
            if ($hostname === $central || str_ends_with($hostname, '.'.$central)) {
                throw DomainException::hostnameIsCentral($hostname);
            }
        }

        $max = (int) config('base-tenant.domains.custom.max_per_account', 3);

        if ($max > 0 && $this->domainsFor($account)->count() >= $max) {
            throw DomainException::tooManyDomains($max);
        }

        if (AccountDomain::query()->where('hostname', $hostname)->exists()) {
            throw DomainException::hostnameTaken($hostname);
        }

        try {
            $domain = AccountDomain::create([
                'account_id' => $account->getKey(),
                'hostname' => $hostname,
                'verification_token' => $this->newToken(),
                'status' => AccountDomain::STATUS_PENDING,
                'is_primary' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw DomainException::hostnameTaken($hostname);
        }

        ActivityLogService::log(
            subject: $account,
            action: 'domain.added',
            newValues: ['hostname' => $hostname],
        );

        return $domain;
    }

    public function removeDomain(AccountDomain $domain): void
    {
        $hostname = $domain->hostname;
        $account = $domain->account;

        $domain->delete();

        ActivityLogService::log(
            subject: $account,
            action: 'domain.removed',
            oldValues: ['hostname' => $hostname],
        );
    }

    /**
     * Check the TXT record and move the domain to verified or failed.
     */
    public function verify(AccountDomain $domain): bool
    {
        // Off means off: verifying, promoting and logging a domain the
        // resolver refuses to serve would leave the two halves disagreeing.
        if (! $this->customDomainsEnabled()) {
            throw DomainException::customDomainsDisabled();
        }

        $account = $domain->account;

        // A row whose account was deleted -- the ordinary churn case, since the
        // TXT record outlives the customer -- must not take the nightly run
        // down with a type error, and is never verified anew: the resolver
        // already refuses to serve it. Its status is left alone so that
        // restoring the account restores the domain exactly as it was.
        if ($account === null) {
            $domain->forceFill([
                'last_checked_at' => now(),
                'last_error' => __('base-tenant::domains.errors.account_gone'),
            ])->save();

            return false;
        }

        $verified = $this->verifier->verify($domain);

        if ($verified) {
            $domain->forceFill([
                'status' => AccountDomain::STATUS_VERIFIED,
                'verified_at' => $domain->verified_at ?? now(),
                'last_checked_at' => now(),
                'last_error' => null,
            ])->save();

            // The first verified domain becomes the primary one, because an
            // account with a verified domain and no primary would still be
            // served on its subdomain, which reads as the verification not
            // having worked.
            if (! $this->domainsFor($account)->contains(fn (AccountDomain $d): bool => $d->is_primary)) {
                $this->makePrimary($domain);
            }

            ActivityLogService::log(
                subject: $account,
                action: 'domain.verified',
                newValues: ['hostname' => $domain->hostname],
            );

            return true;
        }

        $domain->forceFill([
            // A domain that was verified and stops resolving is not demoted on
            // one bad answer: DNS fails transiently, and taking a customer's
            // production hostname out of service for it would be worse than
            // the problem.
            'status' => $domain->isVerified() ? AccountDomain::STATUS_VERIFIED : AccountDomain::STATUS_FAILED,
            'last_checked_at' => now(),
            'last_error' => __('base-tenant::domains.errors.record_not_found'),
        ])->save();

        return false;
    }

    public function makePrimary(AccountDomain $domain): void
    {
        if (! $domain->isVerified()) {
            return;
        }

        DB::transaction(function () use ($domain): void {
            AccountDomain::query()
                ->where('account_id', $domain->account_id)
                ->update(['is_primary' => false]);

            $domain->forceFill(['is_primary' => true])->save();
        });

        ActivityLogService::log(
            subject: $domain->account,
            action: 'domain.primary_changed',
            newValues: ['hostname' => $domain->hostname],
        );
    }

    /**
     * Domains due for a re-check, oldest first.
     *
     * @return Collection<int, AccountDomain>
     */
    public function dueForVerification(): Collection
    {
        $hours = (int) config('base-tenant.domains.custom.verification.recheck_after_hours', 24);

        return AccountDomain::query()
            ->with('account')
            ->whereHas('account')
            ->where(fn ($query) => $query
                ->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<=', now()->subHours($hours)))
            ->orderByRaw('last_checked_at is null desc')
            ->orderBy('last_checked_at')
            ->get();
    }

    /**
     * The one reading of the switch: the screen, the service and the tenant
     * resolver all ask here, so turning custom domains off means the same
     * thing in all three places.
     */
    public function customDomainsEnabled(): bool
    {
        return Module::enabled(Module::DOMAINS)
            && (bool) config('base-tenant.domains.custom.enabled', true);
    }

    /**
     * Not gated on the module: the `subdomain` column and its resolution
     * predate it, and an installation that never enabled the domains module
     * still routes by subdomain.
     */
    public function subdomainsEnabled(): bool
    {
        return (bool) config('base-tenant.domains.subdomains.enabled', true);
    }

    protected function newToken(): string
    {
        return 'base-tenant-verify='.Str::random(40);
    }

    protected function centralDomain(): ?string
    {
        return config('base-tenant.tenancy.central_domains', [])[0] ?? null;
    }

    /**
     * @return Builder<Account>
     */
    protected function accounts()
    {
        $model = config('base-tenant.models.account', Account::class);

        return $model::query();
    }
}
