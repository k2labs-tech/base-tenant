<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Onboarding;

use Base\Tenant\Facades\Onboarding;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The dashboard widget.
 *
 * Renders nothing at all once the account is done or has dismissed it, rather
 * than an empty card: a checklist with every box ticked is a reminder of work
 * already finished, taking up the best space on the page.
 */
class Checklist extends Component
{
    public function dismiss(): void
    {
        Onboarding::dismiss();
    }

    public function render(): View
    {
        return view('base-tenant::livewire.onboarding.checklist', [
            'visible' => Onboarding::shouldShow(),
            'steps' => Onboarding::steps(),
            'progress' => Onboarding::progress(),
        ]);
    }
}
