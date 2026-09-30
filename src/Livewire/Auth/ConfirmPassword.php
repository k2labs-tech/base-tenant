<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Livewire\Attributes\GuestLayout;
use Base\Tenant\Support\Home;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

#[GuestLayout]
class ConfirmPassword extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('base-tenant::auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: Home::url(absolute: false), navigate: true);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.confirm-password');
    }
}
