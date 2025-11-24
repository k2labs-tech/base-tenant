<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Account;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountSwitcher extends Component
{
    public $currentAccountId;
    public $accounts = [];

    public function mount()
    {
        $this->currentAccountId = session('current_account_id');
        $this->loadAccounts();
    }

    protected function loadAccounts()
    {
        $user = Auth::user();
        $multiTeam = config('base-tenant.multi_team', false);

        if ($multiTeam) {
            // In multi-team mode, get all accounts from pivot table
            $this->accounts = $user->accounts()->get();
        } else {
            // In single-team mode, only show primary account
            if ($user->account_id) {
                $this->accounts = Account::where('id', $user->account_id)->get();
            }
        }
    }

    public function switchAccount($accountId)
    {
        $user = Auth::user();

        // Verify user has access to this account
        $hasAccess = false;

        if (config('base-tenant.multi_team', false)) {
            // Multi-team: check pivot table
            $hasAccess = $user->accounts()->where('accounts.id', $accountId)->exists();
        } else {
            // Single-team: check direct account_id
            $hasAccess = $user->account_id === $accountId;
        }

        if (!$hasAccess) {
            return;
        }

        // Update session
        session(['current_account_id' => $accountId]);
        $this->currentAccountId = $accountId;

        // Refresh roles in session for the new account context
        $user->storeRolesSession();

        // Use JavaScript to reload the page
        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('base-tenant::livewire.account-switcher');
    }
}
