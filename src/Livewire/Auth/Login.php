<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.guest')]
class Login extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        // Only redirect to dashboard if not already redirecting (e.g., to 2FA)
        if (! session()->has('2fa.user_id')) {
            Session::regenerate();
            $this->redirectIntended(default: route('base-tenant.dashboard', absolute: false), navigate: true);
        }
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.login');
    }
}
