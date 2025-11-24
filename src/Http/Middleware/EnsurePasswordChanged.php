<?php

namespace Base\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if feature is enabled
        if (!config('base-tenant.force_password_change.enabled', false)) {
            return $next($request);
        }

        // If user is authenticated and must change password
        if ($user && $user->must_change_password) {
            // Allow access to change password route, logout, and livewire endpoints
            $allowedRoutes = [
                'base-tenant.password.change',
                'logout',
            ];

            // Also allow Livewire requests
            if ($request->routeIs($allowedRoutes) ||
                $request->is('livewire/*') ||
                $request->header('X-Livewire')) {
                return $next($request);
            }

            // Redirect to change password page
            return redirect()->route('base-tenant.password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return $next($request);
    }
}
