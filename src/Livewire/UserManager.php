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

    public $showDeleteModal = false;

    public $deletingUser = null;

    public $search = '';

    public function mount()
    {
        // Check if user is a system admin or account owner
        $user = Auth::user();

        // System admins (is_admin = true or no account_id) can access all users
        if ($user->is_admin || is_null($user->account_id)) {
            return;
        }

        // Otherwise, must be account owner
        $account = $user->account;
        if (! $account || $account->user_id !== $user->id) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }
    }

    public function render()
    {
        $user = Auth::user();

        // System admins see all users, others see only their account's users
        if ($user->is_admin || is_null($user->account_id)) {
            // Admin users see all users in the system
            $users = User::query()
                ->with('roles', 'accounts')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        } else {
            // Regular users see only their account's users
            $account = $user->account;
            $users = $account->users()
                ->with('roles')
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(10);
        }

        $roles = Role::nonSystem()->orderBy('name')->get();

        return view('base-tenant::livewire.user-manager', [
            'users' => $users,
            'roles' => $roles,
            'isSystemAdmin' => $user->is_admin || is_null($user->account_id),
        ]);
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if ($this->deletingUser->id === Auth::id()) {
            Flux::toast(
                variant: 'danger',
                heading: __('base-tenant::users.error_deleting_user'),
                text: __('base-tenant::users.cannot_delete_own_account'),
            );
            $this->showDeleteModal = false;

            return;
        }

        $user = Auth::user();

        // System admins can delete users completely
        if ($user->is_admin || is_null($user->account_id)) {
            // For system admins, completely delete the user
            $this->deletingUser->delete();
        } else {
            // For account owners, just detach from their account
            $this->deletingUser->accounts()->detach($user->account_id);

            if ($this->deletingUser->accounts()->count() === 0) {
                $this->deletingUser->delete();
            }
        }

        $this->showDeleteModal = false;
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
}
