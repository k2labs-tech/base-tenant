<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Exceptions\DomainException;
use Base\Tenant\Facades\Domain;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountDomain;
use Base\Tenant\Support\Module;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Where this account's product lives: its subdomain, and the domains of its
 * own it has pointed here.
 *
 * A tenant screen, unlike the language catalogue: the names belong to the
 * customer, and an administrator of the account is the person who should be
 * able to change them.
 */
#[Layout('base-tenant::layouts.app')]
class DomainManager extends Component
{
    public string $subdomain = '';

    public string $hostname = '';

    public function mount(): void
    {
        Module::ensure(Module::DOMAINS);

        abort_unless(Auth::user()->hasPermission('domains.view'), 403);

        $this->subdomain = (string) $this->account()->subdomain;
    }

    public function render(): View
    {
        $account = $this->account();

        return view('base-tenant::livewire.domain-manager', [
            'account' => $account,
            'domains' => $this->domains(),
            'subdomainsEnabled' => (bool) config('base-tenant.domains.subdomains.enabled', true),
            'customEnabled' => (bool) config('base-tenant.domains.custom.enabled', true),
            'centralDomain' => config('base-tenant.tenancy.central_domains', [])[0] ?? null,
            'cnameTarget' => config('base-tenant.domains.custom.target')
                ?: (config('base-tenant.tenancy.central_domains', [])[0] ?? null),
            'currentUrl' => Domain::urlFor($account),
            'canUpdate' => Auth::user()->hasPermission('domains.update'),
            'maxDomains' => (int) config('base-tenant.domains.custom.max_per_account', 3),
        ]);
    }

    /**
     * Live feedback while the field is being typed. Telling somebody the name
     * is taken after they submit a form is the same information arriving too
     * late to be useful.
     */
    public function updatedSubdomain(): void
    {
        $this->resetValidation('subdomain');

        $candidate = Domain::normalizeSubdomain($this->subdomain);

        if ($candidate === '' || $candidate === $this->account()->subdomain) {
            return;
        }

        if (! Domain::isSubdomainWellFormed($candidate)) {
            $this->addError('subdomain', __('base-tenant::domains.errors.subdomain_invalid', [
                'min' => (int) config('base-tenant.domains.subdomains.min_length', 3),
                'max' => (int) config('base-tenant.domains.subdomains.max_length', 63),
            ]));

            return;
        }

        if (! Domain::isSubdomainAvailable($candidate, $this->account())) {
            $this->addError('subdomain', __('base-tenant::domains.errors.subdomain_taken', [
                'subdomain' => $candidate,
            ]));
        }
    }

    public function saveSubdomain(): void
    {
        $this->authorizeUpdating();

        try {
            Domain::claimSubdomain($this->account(), $this->subdomain);
        } catch (DomainException $exception) {
            $this->addError('subdomain', $exception->getMessage());

            return;
        }

        $this->subdomain = (string) $this->account()->refresh()->subdomain;

        Flux::toast(text: __('base-tenant::domains.subdomain_saved'), variant: 'success');
    }

    public function addDomain(): void
    {
        $this->authorizeUpdating();

        try {
            Domain::addDomain($this->account(), $this->hostname);
        } catch (DomainException $exception) {
            $this->addError('hostname', $exception->getMessage());

            return;
        }

        $this->hostname = '';

        Flux::toast(text: __('base-tenant::domains.domain_added'), variant: 'success');
    }

    public function verifyDomain(int $id): void
    {
        $this->authorizeUpdating();

        $domain = $this->findDomain($id);

        if (Domain::verify($domain)) {
            Flux::toast(text: __('base-tenant::domains.domain_verified'), variant: 'success');

            return;
        }

        Flux::toast(text: __('base-tenant::domains.domain_not_verified'), variant: 'warning');
    }

    public function makePrimary(int $id): void
    {
        $this->authorizeUpdating();

        Domain::makePrimary($this->findDomain($id));

        Flux::toast(text: __('base-tenant::domains.primary_updated'), variant: 'success');
    }

    public function removeDomain(int $id): void
    {
        $this->authorizeUpdating();

        Domain::removeDomain($this->findDomain($id));

        Flux::toast(text: __('base-tenant::domains.domain_removed'), variant: 'success');
    }

    /**
     * @return Collection<int, AccountDomain>
     */
    protected function domains(): Collection
    {
        return Domain::domainsFor($this->account());
    }

    /**
     * Scoped by hand, and it has to be: `AccountDomain` carries no global
     * scope, because the tenant resolver reads it before a tenant exists. This
     * is the boundary that keeps one account from acting on another's row.
     */
    protected function findDomain(int $id): AccountDomain
    {
        return AccountDomain::query()
            ->where('account_id', $this->account()->getKey())
            ->findOrFail($id);
    }

    protected function account(): Account
    {
        $account = Tenant::current();

        abort_if($account === null, 404);

        return $account;
    }

    protected function authorizeUpdating(): void
    {
        abort_unless(Auth::user()->hasPermission('domains.update'), 403);
    }
}
