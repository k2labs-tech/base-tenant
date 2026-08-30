<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Presale;

use Base\Tenant\Facades\Presale;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The public price list, rendered from the configured plans.
 *
 * Useful outside pre-sale too: a pricing page built from the same config the
 * feature gates read cannot promise something the product does not enforce.
 */
class PricingTable extends Component
{
    public function render(): View
    {
        return view('base-tenant::livewire.presale.pricing-table', [
            'plans' => config('base-tenant.plans', []),
            'presale' => Presale::isOpen(),
            'seatsLeft' => Presale::isOpen() ? Presale::seatsLeft() : null,
            'soldOut' => Presale::isOpen() && Presale::soldOut(),
            'foundingPlan' => config('base-tenant.presale.plan_after'),
        ]);
    }
}
