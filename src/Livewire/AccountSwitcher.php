<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Services\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Which account the next request will be about.
 *
 * For a customer this is the list they belong to. For platform staff it is
 * every account there is: an administrator with no account of their own cannot
 * otherwise touch anything account-scoped -- menus, settings, features -- and
 * the screens for those simply refuse, which reads as a broken product rather
 * than as a missing context.
 */
class AccountSwitcher extends Component
{
    /**
     * How many accounts a dropdown can usefully show before the search box is
     * the only way through it.
     */
    public const SEARCH_THRESHOLD = 10;

    public ?string $currentAccountId = null;

    public string $search = '';

    public function mount(): void
    {
        $this->currentAccountId = Tenant::currentId();
    }

    public function switchAccount(string $accountId): void
    {
        $user = Auth::user();

        // Staff may enter any account; everybody else only their own. Checked
        // here and not only in the list, because the id arrives from the
        // browser and a list that omits an account does not stop anyone
        // naming it.
        if (! $user->isSuperAdmin() && ! $user->belongsToAccount($accountId)) {
            return;
        }

        $account = Account::query()->whereKey($accountId)->first();

        if (! $account) {
            return;
        }

        Tenant::set($accountId, remember: true);

        $user->forceFill(['last_account_id' => $accountId])->save();
        $user->storeRolesSession();

        $this->currentAccountId = $accountId;

        // Staff entering a customer's account leaves a trace. They are about
        // to act on data that is not theirs, and the account's own audit trail
        // is where that belongs.
        if ($user->isSuperAdmin()) {
            $this->recordEntry($account);
        }

        $this->redirect(route('base-tenant.dashboard'), navigate: false);
    }

    /**
     * Step back out to the platform view.
     *
     * Only staff have one to step back to: everybody else is always inside an
     * account, and leaving would put them somewhere with nothing on it.
     */
    public function leaveAccount(): void
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return;
        }

        Tenant::forget();

        $user->forceFill(['last_account_id' => null])->save();
        $user->storeRolesSession();

        $this->currentAccountId = null;

        $this->redirect(route('base-tenant.dashboard'), navigate: false);
    }

    public function render(): View
    {
        $accounts = $this->availableAccounts();

        return view('base-tenant::livewire.account-switcher', [
            'accounts' => $accounts,
            'isStaff' => Auth::user()->isSuperAdmin(),
            'current' => $this->currentAccountId
                ? Account::query()->whereKey($this->currentAccountId)->first()
                : null,
            'searchable' => Auth::user()->isSuperAdmin() && $this->totalAccounts() > self::SEARCH_THRESHOLD,
        ]);
    }

    /** @return Collection<int, Account> */
    protected function availableAccounts(): Collection
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            // Capped rather than unbounded: an installation with four thousand
            // customers would otherwise build four thousand rows to draw a
            // dropdown. The search narrows it, and the cap is what makes the
            // search necessary rather than decorative.
            return Account::query()
                ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
                ->orderBy('name')
                ->limit(self::SEARCH_THRESHOLD * 5)
                ->get();
        }

        if (config('base-tenant.multi_team', false)) {
            return $user->accounts()->orderBy('name')->get();
        }

        return $user->account
            ? new Collection([$user->account])
            : new Collection;
    }

    protected function totalAccounts(): int
    {
        return Account::query()->count();
    }

    protected function recordEntry(Account $account): void
    {
        if (! config('base-tenant.activity_log.enabled', true)) {
            return;
        }

        Tenant::runFor($account, fn () => ActivityLogService::log(
            action: 'account.entered',
            description: __('base-tenant::accounts.staff_entered', [
                'name' => Auth::user()->name,
            ]),
            subject: $account,
        ));
    }
}
