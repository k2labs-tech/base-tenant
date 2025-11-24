<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EditUser extends Component
{
    public ?User $user = null;

    public $isCreateMode = false;

    // User fields
    public $name;

    public $email;

    public $phone;

    public $timezone;

    public $locale;

    public $currency;

    public $decimal_places;

    public $decimals_separator;

    public $thousands_separator;

    public $date_format;

    public $time_format;

    // Password fields
    public $password;

    public $password_confirmation;

    // Role management
    public $selectedRoles = [];

    // Account selection (for super-admin creating users)
    public $selected_account_id;

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => 'nullable|string|max:20',
        'timezone' => 'nullable|string',
        'locale' => 'nullable|string|max:5',
        'currency' => 'nullable|string|max:3',
        'decimal_places' => 'nullable|integer|min:0|max:4',
        'decimals_separator' => 'nullable|string|max:1',
        'thousands_separator' => 'nullable|string|max:1',
        'date_format' => 'nullable|string|max:20',
        'time_format' => 'nullable|string|max:20',
    ];

    public function mount($user = null)
    {
        $authUser = Auth::user();
        // System admins can manage all users
        $isSystemAdmin = $authUser->is_admin || is_null($authUser->account_id);

        if (! $isSystemAdmin) {
            // Check if user has project-admin role
            $isProjectAdmin = $authUser->hasRole('project-admin');

            if (! $isProjectAdmin) {
                abort(403, __('base-tenant::auth.unauthorized'));
            }
        }

        // Check if we're in create mode by checking the route
        if (request()->route()->getName() === 'base-tenant.users.create') {
            // Create mode
            $this->isCreateMode = true;
            $this->user = new User;

            // Initialize with defaults
            $this->timezone = 'UTC';
            $this->locale = 'en';
            $this->currency = 'USD';
            $this->decimal_places = 2;
            $this->decimals_separator = '.';
            $this->thousands_separator = ',';
            $this->date_format = 'Y-m-d';
            $this->time_format = 'H:i';
        } elseif ($user instanceof User && $user->exists) {
            // Edit mode
            // System admins can edit any user, others must check account membership
            if (! $isSystemAdmin) {
                // Check if user has project-admin role
                $isProjectAdmin = $authUser->hasRole('project-admin');

                if (! $isProjectAdmin) {
                    abort(403, __('base-tenant::auth.unauthorized'));
                }

                // Verify the user being edited belongs to the current account
                $currentAccountId = session('current_account_id');
                if ($currentAccountId && ! $user->accounts->contains($currentAccountId)) {
                    abort(404);
                }
            }

            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->phone;
            $this->timezone = $user->timezone;
            $this->locale = $user->locale;
            $this->currency = $user->currency;
            $this->decimal_places = $user->decimal_places;
            $this->decimals_separator = $user->decimals_separator;
            $this->thousands_separator = $user->thousands_separator;
            $this->date_format = $user->date_format;
            $this->time_format = $user->time_format;
            $this->selectedRoles = $user->roles->pluck('id')->toArray();
        } else {
            // If we can't determine the mode, abort
            abort(404);
        }
    }

    public function render()
    {
        $roles = Role::nonSystem()->orderBy('name')->get();

        // Get all accounts for super-admin to select when creating users
        $accounts = collect();
        if ($this->isCreateMode && Auth::user()->is_admin) {
            $accounts = \Base\Tenant\Models\Account::orderBy('name')->get();
        }

        // Get account users if user is project-admin and has a primary account
        $accountUsers = collect();
        $isProjectAdmin = false;

        if (!$this->isCreateMode) {
            // Load user roles if not already loaded
            if (!$this->user->relationLoaded('roles')) {
                $this->user->load('roles');
            }

            // Check if user has project-admin role
            $isProjectAdmin = $this->user->roles->contains(function ($role) {
                return $role->key === 'project-admin';
            });

            // Get other users in the same primary account
            // Uses account_id field (primary account) instead of multi-account pivot table
            if ($isProjectAdmin && $this->user->account_id) {
                $accountUsers = User::where('account_id', $this->user->account_id)
                    ->where('id', '!=', $this->user->id)
                    ->with('roles')
                    ->orderBy('name')
                    ->get();
            }
        }

        return view('base-tenant::livewire.edit-user', [
            'roles' => $roles,
            'accounts' => $accounts,
            'accountUsers' => $accountUsers,
            'isProjectAdmin' => $isProjectAdmin,
            'timezones' => timezone_identifiers_list(),
            'locales' => [
                'en' => __('base-tenant::languages.english'),
                'es' => __('base-tenant::languages.spanish'),
            ],
            'currencies' => [
                'USD' => 'USD - '.__('base-tenant::currencies.usd'),
                'EUR' => 'EUR - '.__('base-tenant::currencies.eur'),
                'GBP' => 'GBP - '.__('base-tenant::currencies.gbp'),
            ],
            'dateFormats' => [
                'Y-m-d' => date('Y-m-d').' (Y-m-d)',
                'd/m/Y' => date('d/m/Y').' (d/m/Y)',
                'm/d/Y' => date('m/d/Y').' (m/d/Y)',
                'd.m.Y' => date('d.m.Y').' (d.m.Y)',
            ],
            'timeFormats' => [
                'H:i' => date('H:i').' (24h)',
                'h:i A' => date('h:i A').' (12h)',
            ],
        ]);
    }

    public function updateProfileInformation()
    {
        if ($this->isCreateMode) {
            $this->saveNewUser();

            return;
        }

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $this->user->update($validated);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.user_updated'),
            text: __('base-tenant::users.profile_updated'),
        );
    }

    public function saveNewUser()
    {
        $authUser = Auth::user();
        $isSystemAdmin = $authUser->is_admin || is_null($authUser->account_id);

        // Build validation rules
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'exists:roles,id',
        ];

        // Super-admin must select an account
        if ($isSystemAdmin) {
            $validationRules['selected_account_id'] = 'required|exists:accounts,id';
        }

        $validated = $this->validate($validationRules);

        // Determine account_id: super-admin selects it, regular users use current session account
        $accountId = $isSystemAdmin ? $this->selected_account_id : session('current_account_id');

        $multiTeam = config('base-tenant.multi_team', false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'account_id' => $accountId,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'decimal_places' => $this->decimal_places,
            'decimals_separator' => $this->decimals_separator,
            'thousands_separator' => $this->thousands_separator,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
        ]);

        // Always attach to account_user pivot table for forward compatibility
        // This allows switching between single-team and multi-team modes
        if ($accountId) {
            $user->accounts()->attach($accountId);
        }

        // Attach roles with account_id in pivot (for account-scoped roles)
        if ($accountId && !empty($this->selectedRoles)) {
            // Attach roles with account_id for account-scoped roles
            foreach ($this->selectedRoles as $roleId) {
                $user->roles()->attach($roleId, ['account_id' => $accountId]);
            }
        } else {
            // For system admins without account, attach roles globally
            $user->roles()->sync($this->selectedRoles);
        }

        // Store roles in session
        $user->storeRolesSession();

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.user_created'),
            text: __('base-tenant::users.created_successfully'),
        );

        // Redirect to edit mode
        return redirect()->route('base-tenant.users.edit', $user);
    }

    public function updatePassword()
    {
        if ($this->isCreateMode) {
            return; // Password is handled in saveNewUser
        }

        $validated = $this->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $this->user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset(['password', 'password_confirmation']);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.password_updated_title'),
            text: __('base-tenant::users.password_updated'),
        );
    }

    public function updatePreferences()
    {
        if ($this->isCreateMode) {
            return; // Preferences are handled in saveNewUser
        }

        $validated = $this->validate([
            'timezone' => 'nullable|string',
            'locale' => 'nullable|string|max:5',
            'currency' => 'nullable|string|max:3',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'decimals_separator' => 'nullable|string|max:1',
            'thousands_separator' => 'nullable|string|max:1',
            'date_format' => 'nullable|string|max:20',
            'time_format' => 'nullable|string|max:20',
        ]);

        $this->user->update($validated);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.preferences_updated_title'),
            text: __('base-tenant::users.preferences_updated'),
        );
    }

    public function updateRoles()
    {
        if ($this->isCreateMode) {
            return; // Roles are handled in saveNewUser
        }

        // Detach all current roles
        $this->user->roles()->detach();

        // Attach new roles with account_id if user has an account
        if ($this->user->account_id && !empty($this->selectedRoles)) {
            foreach ($this->selectedRoles as $roleId) {
                $this->user->roles()->attach($roleId, ['account_id' => $this->user->account_id]);
            }
        } else {
            // For users without account (system admins), attach roles globally
            $this->user->roles()->sync($this->selectedRoles);
        }

        // Update roles in session
        $this->user->storeRolesSession();

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.roles_updated_title'),
            text: __('base-tenant::users.roles_updated'),
        );
    }
}
