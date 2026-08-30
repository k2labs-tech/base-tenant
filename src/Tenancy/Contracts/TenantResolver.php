<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Contracts;

use Base\Tenant\Models\Account;
use Illuminate\Http\Request;

interface TenantResolver
{
    /**
     * Resolve the account for the given request, or null when this
     * resolver cannot determine one.
     */
    public function resolve(Request $request): ?Account;
}
