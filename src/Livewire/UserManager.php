<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserManager extends Component
{
    use WithPagination;

    public $deletingUser = null;

    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);

        // System admins or users with any custom role can access
        if (! $isSystemAdmin && ! $user->hasAnyCustomRole()) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }
    }

    public function render()
    {
        $user = Auth::user();
        $currentAccountId = session('current_account_id');

        // System admins see all users, others see only their account's users
        if ($user->is_admin || is_null($user->account_id)) {
            // Admin users see all users in the system
            $users = User::query()
                ->with('roles', 'account')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        } else {
            // Project admins see only their account's users
            // In multi-team mode, use the pivot table; otherwise use account_id
            $multiTeam = config('base-tenant.multi_team', false);

            if ($multiTeam) {
                // Multi-team: get users from account_user pivot table
                $users = User::query()
                    ->whereHas('accounts', function ($query) use ($currentAccountId) {
                        $query->where('accounts.id', $currentAccountId);
                    })
                    ->with([
                        'roles' => function ($query) use ($currentAccountId) {
                            $query->wherePivot('account_id', $currentAccountId);
                        },
                        'account'
                    ])
                    ->when($this->search, function ($query) {
                        $query->where(function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                    })
                    ->orderBy('name')
                    ->paginate(10);
            } else {
                // Single-team: get users by account_id field
                $users = User::query()
                    ->where('account_id', $currentAccountId)
                    ->with([
                        'roles' => function ($query) use ($currentAccountId) {
                            $query->wherePivot('account_id', $currentAccountId);
                        },
                        'account'
                    ])
                    ->when($this->search, function ($query) {
                        $query->where(function ($q) {
                            $q->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                    })
                    ->orderBy('name')
                    ->paginate(10);
            }
        }

        $roles = Role::nonSystem()->orderBy('name')->get();

        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $canEdit = $isSystemAdmin || $user->hasPrimaryRole();

        return view('base-tenant::livewire.user-manager', [
            'users' => $users,
            'roles' => $roles,
            'isSystemAdmin' => $isSystemAdmin,
            'canEdit' => $canEdit,
        ]);
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->modal('delete-user-modal')->show();
    }

    public function deleteUser()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);

        // Only system admins and primary role users can delete users
        if (! $isSystemAdmin && ! $user->hasPrimaryRole()) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }

        if ($this->deletingUser->id === Auth::id()) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::users.error_deleting_user'),
                text: __('base-tenant::users.cannot_delete_own_account'),
            );
            $this->modal('delete-user-modal')->close();

            return;
        }

        // System admins can delete users completely
        if ($isSystemAdmin) {
            // For system admins, completely delete the user
            $this->deletingUser->delete();
        } else {
            // For project admins, verify they're deleting a user from the current account
            $currentAccountId = session('current_account_id');
            $multiTeam = config('base-tenant.multi_team', false);

            if ($multiTeam) {
                // Multi-team: check if user belongs to current account via pivot
                if (!$this->deletingUser->accounts->contains($currentAccountId)) {
                    abort(403, __('base-tenant::auth.unauthorized'));
                }
            } else {
                // Single-team: check direct account_id
                if ($this->deletingUser->account_id !== $currentAccountId) {
                    abort(403, __('base-tenant::auth.unauthorized'));
                }
            }

            // Remove the user completely from the system
            $this->deletingUser->delete();
        }

        $this->modal('delete-user-modal')->close();
        $this->deletingUser = null;

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.user_removed'),
            text: __('base-tenant::users.removed_successfully'),
        );
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function impersonate($userId)
    {
        $user = Auth::user();

        // Only system admins can impersonate
        if (! $user->canImpersonate()) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }

        $targetUser = User::findOrFail($userId);

        // Cannot impersonate system admins
        if (! $targetUser->canBeImpersonated()) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::users.cannot_impersonate'),
                text: __('base-tenant::users.cannot_impersonate_admin'),
            );
            return;
        }

        $user->impersonate($targetUser);

        // Refresh roles in session for the impersonated user
        auth()->user()->storeRolesSession();

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.impersonating'),
            text: __('base-tenant::users.impersonating_as', ['name' => $targetUser->name]),
        );

        return redirect()->route('base-tenant.dashboard');
    }
}
