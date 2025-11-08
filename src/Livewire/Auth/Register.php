<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Models\User;
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

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'companyName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $user->createPrimaryAccountAndSetRole($validated['companyName']);

        Auth::login($user);

        $this->redirect(route('base-tenant.checkout'), navigate: false);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.register');
    }
}
