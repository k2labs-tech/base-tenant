<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
//use WireUi\Traits\WireUiActions;

#[Layout('layouts.app')]
class EditUser extends Component
{
//    use WireUiActions;

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
            // Non-admins must be account owners
            $account = $authUser->account;
            if ($account && $account->user_id !== $authUser->id) {
                abort(403, __('base-tenant::auth.unauthorized'));
            }
        }

        // Check if we're in create mode by checking the route
        if (request()->route()->getName() === 'users.create') {
            // Create mode
            $this->isCreateMode = true;
            $this->user = new User;
        } elseif ($user instanceof User && $user->exists) {
            // Edit mode
            // System admins can edit any user, others must check account membership
            if (! $isSystemAdmin && $authUser->account) {
                if (! $user->accounts->contains($authUser->account->id)) {
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

        return view('base-tenant::livewire.edit-user', [
            'roles' => $roles,
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

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('base-tenant::users.user_updated'),
            'description' => __('base-tenant::users.profile_updated'),
        ]);
    }

    public function saveNewUser()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $authUser = Auth::user();
        $isSystemAdmin = $authUser->is_admin || is_null($authUser->account_id);

        // System admins can create users without account assignment
        // Regular users create within their account
        $accountId = $isSystemAdmin ? null : $authUser->account_id;

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

        // Only attach to accounts if not a system admin user
        if (! $isSystemAdmin && $authUser->account_id) {
            $user->accounts()->attach($authUser->account_id);
        }

        $user->roles()->sync($this->selectedRoles);
        $this->notification()->send([
            'icon' => 'success',
            'title' => __('base-tenant::users.user_created'),
            'description' => __('base-tenant::users.created_successfully'),
        ]);

        // Redirect to edit mode
        return redirect()->route('users.edit', $user);
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
        $this->notification()->send([
            'icon' => 'success',
            'title' => __('base-tenant::users.password_updated_title'),
            'description' => __('base-tenant::users.password_updated'),
        ]);
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

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('base-tenant::users.preferences_updated_title'),
            'description' => __('base-tenant::users.preferences_updated'),
        ]);
    }

    public function updateRoles()
    {
        if ($this->isCreateMode) {
            return; // Roles are handled in saveNewUser
        }

        $this->user->roles()->sync($this->selectedRoles);
        $this->notification()->send([
            'icon' => 'success',
            'title' => __('base-tenant::users.roles_updated_title'),
            'description' => __('base-tenant::users.roles_updated'),
        ]);
    }
}
