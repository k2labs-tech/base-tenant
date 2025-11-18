<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EditAccount extends Component
{
    public ?Account $account = null;

    public bool $isCreateMode = false;

    // Account fields
    public $name = '';

    public $active = true;

    public $email = '';

    public $phone = '';

    public $address = '';

    public $city = '';

    public $state = '';

    public $country = '';

    public $postal_code = '';

    public $vat = '';

    public $selected_owner_id = null;

    public function mount(?Account $account = null)
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $isProjectAdmin = $user->hasRole('project-admin');

        // System admins or project-admins can access
        if (! $isSystemAdmin && ! $isProjectAdmin) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }

        // If project-admin, verify they're editing their own account
        if ($isProjectAdmin && ! $isSystemAdmin) {
            if ($account && $account->exists && $account->id !== $user->account_id) {
                abort(403, __('base-tenant::auth.unauthorized'));
            }
            // If no account provided, load their account
            if (! $account || ! $account->exists) {
                $account = Account::find($user->account_id);
                if (! $account) {
                    abort(404);
                }
            }
        }

        if ($account && $account->exists) {
            $this->account = $account;
            $this->isCreateMode = false;
            $this->name = $account->name;
            $this->active = (bool) $account->active;
            $this->email = $account->email ?? '';
            $this->phone = $account->phone ?? '';
            $this->address = $account->address ?? '';
            $this->city = $account->city ?? '';
            $this->state = $account->state ?? '';
            $this->country = $account->country ?? '';
            $this->postal_code = $account->postal_code ?? '';
            $this->vat = $account->vat ?? '';
            $this->selected_owner_id = $account->user_id;
        } else {
            $this->isCreateMode = true;
            $this->account = new Account;
        }
    }

    public function saveAccount()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);

        // Both system admins and project admins can edit all fields
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'active' => 'boolean',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'vat' => 'nullable|string|max:50',
            'selected_owner_id' => 'nullable|exists:users,id',
        ]);

        if ($this->isCreateMode) {
            $account = Account::create([
                'name' => $validated['name'],
                'active' => $validated['active'] ?? true,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'country' => $validated['country'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'vat' => $validated['vat'] ?? null,
                'user_id' => $validated['selected_owner_id'] ?? null,
            ]);

            Flux::toast(
                variant: 'success',
                heading: __('base-tenant::accounts.account_created'),
                text: __('base-tenant::accounts.created_successfully'),
            );

            return redirect()->route('base-tenant.accounts.edit', $account);
        } else {
            $this->account->update([
                'name' => $validated['name'],
                'active' => $validated['active'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'country' => $validated['country'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'vat' => $validated['vat'] ?? null,
                'user_id' => $validated['selected_owner_id'] ?? null,
            ]);

            Flux::toast(
                variant: 'success',
                heading: __('base-tenant::accounts.account_updated'),
                text: __('base-tenant::accounts.updated_successfully'),
            );
        }
    }

    public function render()
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);

        // Get all users for owner selection
        $users = User::orderBy('name')->get();

        // Get account users if editing
        $accountUsers = collect();
        if (! $this->isCreateMode && $this->account->id) {
            $accountUsers = $this->account->users()
                ->with('roles')
                ->orderBy('name')
                ->get();
        }

        return view('base-tenant::livewire.edit-account', [
            'users' => $users,
            'accountUsers' => $accountUsers,
            'isSystemAdmin' => $isSystemAdmin,
        ]);
    }
}
