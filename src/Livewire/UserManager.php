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
        $isProjectAdmin = $user->hasRole('project-admin');
        $isProjectCollaborator = $user->hasRole('project-collaborator');

        // System admins, project-admins, or project-collaborators can access
        if (! $isSystemAdmin && ! $isProjectAdmin && ! $isProjectCollaborator) {
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
            // Project admins see only their account's users (by account_id)
            $users = User::query()
                ->where('account_id', $user->account_id)
                ->with('roles', 'account')
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

        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $isProjectAdmin = $user->hasRole('project-admin');
        $canEdit = $isSystemAdmin || $isProjectAdmin;

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
        $isProjectAdmin = $user->hasRole('project-admin');

        // Only system admins and project admins can delete users
        if (! $isSystemAdmin && ! $isProjectAdmin) {
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
            // For project admins, verify they're deleting a user from their account
            if ($this->deletingUser->account_id !== $user->account_id) {
                abort(403, __('base-tenant::auth.unauthorized'));
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
