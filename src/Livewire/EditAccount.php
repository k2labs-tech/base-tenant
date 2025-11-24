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

    public $force_password_change = null;

    public function mount(?Account $account = null)
    {
        $user = Auth::user();
        $isSystemAdmin = $user->is_admin || is_null($user->account_id);
        $isProjectAdmin = $user->hasRole('project-admin');

        // System admins or project-admins can access
        if (! $isSystemAdmin && ! $isProjectAdmin) {
            abort(403, __('base-tenant::auth.unauthorized'));
        }

        // If project-admin, verify they're editing an account they have access to
        if ($isProjectAdmin && ! $isSystemAdmin) {
            $currentAccountId = session('current_account_id');
            $multiTeam = config('base-tenant.multi_team', false);

            if ($account && $account->exists) {
                // Verify they have access to this account
                if ($multiTeam) {
                    // Multi-team: check via pivot
                    if (!$user->accounts->contains($account->id)) {
                        abort(403, __('base-tenant::auth.unauthorized'));
                    }
                } else {
                    // Single-team: check if it's current account
                    if ($account->id !== $currentAccountId) {
                        abort(403, __('base-tenant::auth.unauthorized'));
                    }
                }
            } else {
                // If no account provided, load current account
                $account = Account::find($currentAccountId);
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
            $this->force_password_change = $account->force_password_change;
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
            'force_password_change' => 'nullable|boolean',
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
                'force_password_change' => $validated['force_password_change'] ?? null,
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
                'force_password_change' => $validated['force_password_change'] ?? null,
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

        // Check if force password change feature is enabled globally
        $globalForcePasswordChangeEnabled = config('base-tenant.force_password_change.enabled', false);

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
            'globalForcePasswordChangeEnabled' => $globalForcePasswordChangeEnabled,
        ]);
    }
}
