<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Livewire\Component;

class Logout extends Component
{
    public function logout(): void
    {
        auth()->guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('base-tenant::livewire.logout');
    }
}
