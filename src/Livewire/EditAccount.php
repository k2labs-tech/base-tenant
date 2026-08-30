<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.app')]
class EditAccount extends Component
{
    public ?Account $account = null;

    public bool $isCreateMode = false;

    public string $name = '';

    public bool $active = true;

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $postal_code = '';

    public string $vat = '';

    public ?string $selected_owner_id = null;

    public string $force_password_change = '';

    public function mount(?Account $account = null): void
    {
        $account ??= Tenant::current();

        if ($account && $account->exists) {
            $this->authorize('update', $account);
            $this->account = $account;
            $this->fillFromAccount($account);

            return;
        }

        $this->authorize('create', Account::class);

        $this->isCreateMode = true;
        $this->account = new Account;
    }

    public function saveAccount(): mixed
    {
        $this->isCreateMode
            ? $this->authorize('create', Account::class)
            : $this->authorize('update', $this->account);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'vat' => ['nullable', 'string', 'max:50'],
            'selected_owner_id' => ['nullable', 'exists:users,id'],
            'force_password_change' => ['nullable', 'in:0,1,'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'active' => $validated['active'] ?? true,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address' => $validated['address'] ?: null,
            'city' => $validated['city'] ?: null,
            'state' => $validated['state'] ?: null,
            'country' => $validated['country'] ?: null,
            'postal_code' => $validated['postal_code'] ?: null,
            'vat' => $validated['vat'] ?: null,
            'user_id' => $validated['selected_owner_id'] ?: null,
            'force_password_change' => $this->forcePasswordChangeValue(),
        ];

        if ($this->isCreateMode) {
            $account = Account::create($attributes);

            Flux::toast(
                variant: 'success',
                heading: __('base-tenant::accounts.account_created'),
                text: __('base-tenant::accounts.created_successfully'),
            );

            return redirect()->route('base-tenant.accounts.edit', $account);
        }

        $this->account->update($attributes);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::accounts.account_updated'),
            text: __('base-tenant::accounts.updated_successfully'),
        );

        return null;
    }

    public function render(): View
    {
        return view('base-tenant::livewire.edit-account', [
            'users' => User::query()->orderBy('name')->get(),
            'accountUsers' => $this->accountUsers(),
            'isSystemAdmin' => Auth::user()->isSuperAdmin(),
            'globalForcePasswordChangeEnabled' => config('base-tenant.force_password_change.enabled', false),
        ]);
    }

    /**
     * Members of the account, with the roles they hold inside it.
     *
     * @return Collection<int, User>
     */
    protected function accountUsers(): Collection
    {
        if ($this->isCreateMode || ! $this->account?->exists) {
            return new Collection;
        }

        return Tenant::runFor(
            $this->account,
            fn (): Collection => $this->account->users()->with('roles')->orderBy('name')->get()
        );
    }

    protected function forcePasswordChangeValue(): ?bool
    {
        return match ($this->force_password_change) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    protected function fillFromAccount(Account $account): void
    {
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

        $this->force_password_change = match ($account->force_password_change) {
            true => '1',
            false => '0',
            default => '',
        };
    }
}
