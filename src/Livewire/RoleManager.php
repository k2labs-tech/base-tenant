<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Role and permission editor: a matrix of roles against the permission
 * catalogue, plus creation of roles owned by the current account.
 */
#[Layout('base-tenant::layouts.app')]
class RoleManager extends Component
{
    public ?string $editingRoleId = null;

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public string $newRoleName = '';

    public bool $showCreateForm = false;

    public bool $showDeleteModal = false;

    public ?string $deletingRoleId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.role-manager', [
            'roles' => $this->roles(),
            'permissionGroups' => $this->permissionGroups(),
            'editingRole' => $this->editingRole(),
            'canCreate' => Auth::user()->can('create', Role::class),
        ]);
    }

    public function edit(string $roleId): void
    {
        $role = $this->findRole($roleId);

        $this->authorize('update', $role);

        $this->editingRoleId = $role->getKey();
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $this->showCreateForm = false;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingRoleId', 'selectedPermissions', 'showCreateForm', 'newRoleName']);
    }

    public function savePermissions(): void
    {
        $role = $this->findRole($this->editingRoleId);

        $this->authorize('update', $role);

        $permissions = Permission::query()
            ->whereIn('name', $this->selectedPermissions)
            ->get();

        $role->syncPermissions($permissions);

        $this->toast(__('base-tenant::roles.permissions_updated'));
    }

    public function toggleCreateForm(): void
    {
        $this->authorize('create', Role::class);

        $this->showCreateForm = ! $this->showCreateForm;
        $this->editingRoleId = null;
    }

    /**
     * Roles created here belong to the account in context, so one tenant's
     * roles never show up for another.
     */
    public function createRole(): void
    {
        $this->authorize('create', Role::class);

        $account = Tenant::current();

        if (! $account) {
            $this->addError('newRoleName', __('base-tenant::roles.no_account'));

            return;
        }

        $this->validate([
            'newRoleName' => ['required', 'string', 'max:255'],
        ]);

        $name = Str::slug($this->newRoleName);

        $exists = Role::query()
            ->where('name', $name)
            ->assignable()
            ->exists();

        if ($exists) {
            $this->addError('newRoleName', __('base-tenant::roles.already_exists'));

            return;
        }

        $role = Role::create([
            'name' => $name,
            'display_name' => $this->newRoleName,
            'guard_name' => config('base-tenant.permissions_guard', 'web'),
            'account_id' => $account->getKey(),
            'is_system' => false,
        ]);

        $this->reset(['newRoleName', 'showCreateForm']);

        $this->edit($role->getKey());

        $this->toast(__('base-tenant::roles.created'));
    }

    /**
     * Stage a role for deletion and open the confirmation.
     *
     * The authorisation runs here as well as in `deleteRole()`: asking about a
     * role the user cannot delete would be a promise the confirmation could
     * not keep.
     */
    public function confirmDelete(string $roleId): void
    {
        $role = $this->findRole($roleId);

        $this->authorize('delete', $role);

        $this->deletingRoleId = $role->getKey();
        $this->showDeleteModal = true;
    }

    public function deleteRole(string $roleId): void
    {
        $role = $this->findRole($roleId);

        $this->authorize('delete', $role);

        $role->delete();

        $this->cancelEdit();

        $this->showDeleteModal = false;
        $this->deletingRoleId = null;

        $this->toast(__('base-tenant::roles.deleted'));
    }

    public function deletingRole(): ?Role
    {
        return $this->deletingRoleId ? $this->findRole($this->deletingRoleId) : null;
    }

    /** @return Collection<int, Role> */
    protected function roles(): Collection
    {
        return Role::query()
            ->manageable()
            ->with('permissions')
            ->orderBy('is_system')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * The permission catalogue grouped for display.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->group ?? 'other');
    }

    protected function editingRole(): ?Role
    {
        return $this->editingRoleId ? $this->findRole($this->editingRoleId) : null;
    }

    /**
     * Scoped to what the editor lists, so a role that is not on screen cannot
     * be reached by id either.
     */
    protected function findRole(string $roleId): Role
    {
        return Role::query()->manageable()->with('permissions')->findOrFail($roleId);
    }

    protected function toast(string $heading): void
    {
        // Flux takes the message as its first argument; a toast with only a
        // heading throws.
        Flux::toast(text: $heading, variant: 'success');
    }
}
