<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Facades\MagicLink;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ask for a sign-in link.
 */
#[Layout('base-tenant::layouts.guest')]
class RequestMagicLink extends Component
{
    public string $email = '';

    /** Set once a link has been asked for, whatever the answer was. */
    public bool $sent = false;

    public function mount(): void
    {
        Module::ensure(Module::PASSWORDLESS);

        abort_unless(MagicLink::enabled(), 404);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.auth.request-magic-link', [
            'ttl' => MagicLink::ttlMinutes(),
        ]);
    }

    public function send(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        if (MagicLink::tooManyRequests($this->email, request())) {
            $this->addError('email', __('base-tenant::passwordless.throttled', [
                'seconds' => MagicLink::secondsUntilRetry($this->email),
            ]));

            return;
        }

        MagicLink::recordRequest($this->email, request());
        MagicLink::request($this->email, request());

        // The same answer whether or not the address belongs to anybody.
        // Anything else turns this form into a way of asking the product "is
        // this person a customer of yours?".
        $this->sent = true;
    }
}
