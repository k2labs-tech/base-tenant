<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Account;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AccountManager extends Component
{
    use WithPagination;

    public $deletingAccount = null;

    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $isProjectAdmin = $user->hasRole('project-admin');

        // System admins or project-admins can access
        if (! $isSystemAdmin && ! $isProjectAdmin) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }
    }

    public function render()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);

        if ($isSystemAdmin) {
            // System admins see all accounts
            $accounts = Account::query()
                ->withCount('users')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        } else {
            // Project admins see only the current account
            $currentAccountId = session('current_account_id');

            $accounts = Account::query()
                ->where('id', $currentAccountId)
                ->withCount('users')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        }

        return view('base-tenant::livewire.account-manager', [
            'accounts' => $accounts,
            'isSystemAdmin' => $isSystemAdmin,
        ]);
    }

    public function confirmDelete(Account $account)
    {
        $this->deletingAccount = $account;
        $this->modal('delete-account-modal')->show();
    }

    public function deleteAccount()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $isProjectAdmin = $user->hasRole('project-admin');

        // Project-admins can only delete accounts they have access to
        if ($isProjectAdmin && ! $isSystemAdmin) {
            $multiTeam = config('base-tenant.multi_team', false);

            if ($multiTeam) {
                // Multi-team: check if user has access to this account via pivot
                if (!$user->accounts->contains($this->deletingAccount->id)) {
                    abort(403, __('base-tenant::auth.unauthorized'));
                }
            } else {
                // Single-team: check if it's their primary account
                $currentAccountId = session('current_account_id');
                if ($this->deletingAccount->id !== $currentAccountId) {
                    abort(403, __('base-tenant::auth.unauthorized'));
                }
            }
        }

        // Check if account has users
        if ($this->deletingAccount->users()->count() > 0) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::accounts.error_deleting_account'),
                text: __('base-tenant::accounts.cannot_delete_with_users'),
            );
            $this->modal('delete-account-modal')->close();

            return;
        }

        // Check for related projects
        $projectsCount = \DB::table('projects')->where('account_id', $this->deletingAccount->id)->count();
        if ($projectsCount > 0) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::accounts.error_deleting_account'),
                text: __('base-tenant::accounts.cannot_delete_with_projects'),
            );
            $this->modal('delete-account-modal')->close();

            return;
        }

        // Check for related translations
        $translationsCount = \DB::table('translations')->where('account_id', $this->deletingAccount->id)->count();
        if ($translationsCount > 0) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::accounts.error_deleting_account'),
                text: __('base-tenant::accounts.cannot_delete_with_translations'),
            );
            $this->modal('delete-account-modal')->close();

            return;
        }

        // Check for any other table with account_id
        $tablesWithRelations = [];
        $tables = \DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $tableName = $table->name;
            if (in_array($tableName, ['accounts', 'users', 'projects', 'translations', 'account_user'])) {
                continue;
            }

            $columns = \DB::select("PRAGMA table_info($tableName)");
            $hasAccountId = false;
            foreach ($columns as $column) {
                if ($column->name === 'account_id') {
                    $hasAccountId = true;
                    break;
                }
            }

            if ($hasAccountId) {
                $count = \DB::table($tableName)->where('account_id', $this->deletingAccount->id)->count();
                if ($count > 0) {
                    $tablesWithRelations[] = $tableName;
                }
            }
        }

        if (count($tablesWithRelations) > 0) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::accounts.error_deleting_account'),
                text: __('base-tenant::accounts.cannot_delete_with_relations', ['tables' => implode(', ', $tablesWithRelations)]),
            );
            $this->modal('delete-account-modal')->close();

            return;
        }

        $this->deletingAccount->delete();

        $this->modal('delete-account-modal')->close();
        $this->deletingAccount = null;

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::accounts.account_removed'),
            text: __('base-tenant::accounts.removed_successfully'),
        );
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
}
