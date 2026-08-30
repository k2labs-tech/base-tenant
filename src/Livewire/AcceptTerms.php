<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The screen a user lands on when the terms have moved on without them.
 */
#[Layout('base-tenant::layouts.app')]
class AcceptTerms extends Component
{
    public bool $accepted = false;

    public function accept(): void
    {
        $this->validate([
            'accepted' => ['accepted'],
        ], [
            'accepted.accepted' => __('base-tenant::gdpr.must_accept'),
        ]);

        Auth::user()->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('base-tenant.gdpr.terms_version'),
        ])->save();

        $this->redirect(route(config('base-tenant.home_url', 'base-tenant.dashboard')), navigate: true);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.accept-terms', [
            'version' => config('base-tenant.gdpr.terms_version'),
            'url' => config('base-tenant.gdpr.terms_url'),
        ]);
    }
}
