<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the active account in context for the request and remembers it in the
 * session so the choice survives navigation.
 */
class SetAccountContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = Tenant::current();

        if ($account && $request->hasSession()) {
            $request->session()->put('current_account_id', $account->getKey());
        }

        return $next($request);
    }
}
