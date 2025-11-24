<?php

namespace Base\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAccountContext
{
    /**
     * Handle an incoming request.
     *
     * Sets the current account context in the session after authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return $next($request); // Guest, skip
        }

        // SuperAdmins don't need account context
        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        // Set account context if not already set
        if (! session()->has('current_account_id')) {
            $accountId = $user->determineDefaultAccount();
            session(['current_account_id' => $accountId]);
        }

        return $next($request);
    }

    /**
     * Check if user is a System Admin
     * System Admins have is_admin flag or null account_id
     */
    protected function isSuperAdmin($user): bool
    {
        return $user->is_admin || is_null($user->account_id);
    }
}
