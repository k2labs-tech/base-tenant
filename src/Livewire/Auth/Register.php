<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.guest')]
class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $companyName = '';
    public string $password_confirmation = '';

    public ?UserInvite $invite = null;
    public string $accountName = '';
    public bool $inviteUsed = false;

    /**
     * Initialize the component, detecting invite token if present.
     */
    public function mount(): void
    {
        $token = request()->query('invite');

        if ($token) {
            $this->invite = UserInvite::findByToken($token);

            if ($this->invite) {
                $this->email = $this->invite->email;
                $this->accountName = $this->invite->account?->name ?? '';
            } elseif (UserInvite::isTokenUsed($token)) {
                $this->inviteUsed = true;
            }
        }
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ];

        if (! $this->invite) {
            $rules['companyName'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        if ($this->invite && strtolower($validated['email']) !== strtolower($this->invite->email)) {
            $this->addError('email', __('base-tenant::invites.email_mismatch'));
            return;
        }

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        if ($this->invite) {
            // Invite flow: join existing account
            $account = $this->invite->account;
            $user->account_id = $account->id;
            $user->save();

            $user->accounts()->attach($account->id);

            $role = $this->invite->role ?? config('base-tenant.registration.invite_default_role');
            $user->addRole($role, $account->id);

            $this->invite->markAsUsed();

            session(['current_account_id' => $account->id]);
        } else {
            // Normal flow: create new account
            $defaultRole = config('base-tenant.registration.default_role', 'customer-admin');
            $account = $user->createPrimaryAccountAndSetRole($validated['companyName'], $defaultRole);
            session(['current_account_id' => $account->id]);
        }

        Auth::login($user);

        $this->redirect(route(config('base-tenant.home_url', 'base-tenant.dashboard')), navigate: false);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.register');
    }
}
