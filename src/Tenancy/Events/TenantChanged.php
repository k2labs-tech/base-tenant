<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Events;

use Base\Tenant\Models\Account;
use Illuminate\Foundation\Events\Dispatchable;

class TenantChanged
{
    use Dispatchable;

    public function __construct(public readonly ?Account $account) {}
}
