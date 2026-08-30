<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Presale;

use Base\Tenant\Facades\Presale;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The embeddable "tell me when it opens" form.
 */
class WaitlistForm extends Component
{
    public string $email = '';

    public string $name = '';

    public string $source = '';

    public bool $joined = false;

    public function mount(string $source = ''): void
    {
        $this->source = $source;
    }

    public function join(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        Presale::join($this->email, [
            'name' => $this->name ?: null,
            'source' => $this->source ?: null,
            'referrer' => request()->headers->get('referer'),
        ]);

        // No "that address is already on the list": signing up twice is not a
        // mistake, and saying so on a waiting list tells a stranger who else
        // is on it.
        $this->joined = true;
    }

    public function render(): View
    {
        return view('base-tenant::livewire.presale.waitlist-form');
    }
}
