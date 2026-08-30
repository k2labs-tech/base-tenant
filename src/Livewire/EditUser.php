<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Notifications\WelcomeUserNotification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.app')]
class EditUser extends Component
{
    public ?User $user = null;

    public bool $isCreateMode = false;

    public ?string $name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $timezone = null;

    public ?string $locale = null;

    public ?string $currency = null;

    public ?int $decimal_places = null;

    public ?string $decimals_separator = null;

    public ?string $thousands_separator = null;

    public ?string $date_format = null;

    public ?string $time_format = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public ?string $selected_account_id = null;

    public function mount(?User $user = null): void
    {
        $this->isCreateMode = request()->routeIs('base-tenant.users.create');

        if ($this->isCreateMode) {
            $this->authorize('create', User::class);
            $this->user = new User;
            $this->applyDefaults();

            if (! $this->canChooseAccount()) {
                $this->selected_account_id = Tenant::currentId();
            }

            return;
        }

        abort_unless($user instanceof User && $user->exists, 404);

        $this->authorize('update', $user);

        $this->user = $user;
        $this->fillFromUser($user);
    }

    public function render(): View
    {
        $view = $this->isCreateMode
            ? 'base-tenant::livewire.create-user'
            : 'base-tenant::livewire.edit-user';

        return view($view, [
            'roles' => $this->assignableRoles(),
            'accounts' => $this->selectableAccounts(),
            'canChooseAccount' => $this->canChooseAccount(),
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

    public function updateProfileInformation(): mixed
    {
        if ($this->isCreateMode) {
            return $this->saveNewUser();
        }

        $this->authorize('update', $this->user);

        $this->user->update($this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$this->user->id],
            'phone' => ['nullable', 'string', 'max:20'],
        ]));

        $this->toast(__('base-tenant::users.user_updated'), __('base-tenant::users.profile_updated'));

        return null;
    }

    public function saveNewUser(): mixed
    {
        $this->authorize('create', User::class);

        $author = Auth::user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['exists:roles,id'],
        ];

        if ($author->isSuperAdmin()) {
            $rules['selected_account_id'] = ['required', 'exists:accounts,id'];
        }

        $validated = $this->validate($rules);

        $accountId = $author->isSuperAdmin() ? $this->selected_account_id : Tenant::currentId();
        $account = $accountId ? Account::find($accountId) : null;

        $forcePasswordChange = $this->shouldForcePasswordChange($account);
        $plainPassword = $validated['password'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
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
            'must_change_password' => $forcePasswordChange,
        ]);

        if ($account) {
            $user->accounts()->syncWithoutDetaching([$account->getKey()]);
            $this->syncRolesFor($user, $account);
        }

        if ($forcePasswordChange && config('base-tenant.force_password_change.send_welcome_email', true)) {
            $user->notify(new WelcomeUserNotification($plainPassword, $author->name));
        }

        $this->toast(__('base-tenant::users.user_created'), __('base-tenant::users.created_successfully'));

        return redirect()->route('base-tenant.users.index');
    }

    public function updatePassword(): void
    {
        if ($this->isCreateMode) {
            return;
        }

        $this->authorize('update', $this->user);

        $validated = $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->user->update(['password' => Hash::make($validated['password'])]);

        $this->reset(['password', 'password_confirmation']);

        $this->toast(
            __('base-tenant::users.password_updated_title'),
            __('base-tenant::users.password_updated')
        );
    }

    public function updatePreferences(): void
    {
        if ($this->isCreateMode) {
            return;
        }

        $this->authorize('update', $this->user);

        $this->user->update($this->validate([
            'timezone' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'max:5'],
            'currency' => ['nullable', 'string', 'max:3'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:4'],
            'decimals_separator' => ['nullable', 'string', 'max:1'],
            'thousands_separator' => ['nullable', 'string', 'max:1'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'time_format' => ['nullable', 'string', 'max:20'],
        ]));

        $this->toast(
            __('base-tenant::users.preferences_updated_title'),
            __('base-tenant::users.preferences_updated')
        );
    }

    /**
     * Replace the user's roles inside the account being edited, leaving the
     * roles they hold in any other account untouched.
     */
    public function updateRoles(): void
    {
        if ($this->isCreateMode) {
            return;
        }

        $this->authorize('update', $this->user);

        $account = $this->accountBeingEdited();

        if (! $account) {
            $this->toast(
                __('base-tenant::users.roles_updated_title'),
                __('base-tenant::users.no_account_for_roles'),
                'warning'
            );

            return;
        }

        $this->syncRolesFor($this->user, $account);

        $this->toast(
            __('base-tenant::users.roles_updated_title'),
            __('base-tenant::users.roles_updated')
        );
    }

    protected function syncRolesFor(User $user, Account $account): void
    {
        $roles = Role::query()
            ->whereIn('id', $this->selectedRoles)
            ->get()
            ->filter(fn (Role $role): bool => $role->isGlobal() || $role->account_id === $account->getKey());

        Tenant::runFor($account, function () use ($user, $roles): void {
            $user->syncRoles($roles);
        });
    }

    protected function accountBeingEdited(): ?Account
    {
        if ($this->user->account_id) {
            return Account::find($this->user->account_id);
        }

        return Tenant::current();
    }

    /** @return Collection<int, Role> */
    protected function assignableRoles(): Collection
    {
        $query = Role::query()->orderBy('display_name');

        if (Auth::user()->isSuperAdmin()) {
            return $query->get();
        }

        return $query->assignable()->nonSystem()->get();
    }

    /**
     * Only staff pick the account a new user lands in. Everyone else still
     * sees the field, filled with the account they are working in and locked:
     * hiding it left them creating people into an account the form never
     * named.
     */
    public function canChooseAccount(): bool
    {
        return Auth::user()->isSuperAdmin();
    }

    /** @return Collection<int, Account> */
    protected function selectableAccounts(): Collection
    {
        if (! $this->isCreateMode) {
            return new Collection;
        }

        if ($this->canChooseAccount()) {
            return Account::query()->orderBy('name')->get();
        }

        $current = Tenant::current();

        return $current ? new Collection([$current]) : new Collection;
    }

    protected function shouldForcePasswordChange(?Account $account): bool
    {
        if (! config('base-tenant.force_password_change.enabled', false) || ! $account) {
            return false;
        }

        return $account->force_password_change === true || $account->force_password_change === null;
    }

    protected function applyDefaults(): void
    {
        $this->timezone = config('app.timezone', 'UTC');
        $this->locale = config('app.locale', 'en');
        $this->currency = 'EUR';
        $this->decimal_places = 2;
        $this->decimals_separator = ',';
        $this->thousands_separator = '.';
        $this->date_format = 'd/m/Y';
        $this->time_format = 'H:i';
    }

    protected function fillFromUser(User $user): void
    {
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

        $account = $this->accountBeingEdited();

        $this->selectedRoles = $account
            ? $user->rolesForAccount($account)->pluck('id')->all()
            : [];
    }

    protected function toast(string $heading, string $text, string $variant = 'success'): void
    {
        Flux::toast(variant: $variant, heading: $heading, text: $text);
    }
}
