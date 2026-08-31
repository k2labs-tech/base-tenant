<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Security;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * End a session that has been idle longer than the account allows.
 *
 * Laravel's `session.lifetime` is one number for the whole installation. This
 * is the customer's own number, which is what a security questionnaire asks
 * about, and it is enforced per request rather than by the session driver so
 * that changing it takes effect immediately.
 */
class EnforceSessionTimeout
{
    protected const KEY = 'base-tenant.last_activity';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Module::enabled(Module::SECURITY) || ! auth()->check() || ! $request->hasSession()) {
            return $next($request);
        }

        $minutes = Security::sessionTimeoutMinutes();

        if ($minutes <= 0) {
            return $next($request);
        }

        $last = $request->session()->get(self::KEY);

        if ($last !== null && (time() - (int) $last) > $minutes * 60) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('base-tenant::security.session_expired'),
                ], 401);
            }

            return redirect()
                ->route('base-tenant.login')
                ->with('status', __('base-tenant::security.session_expired'));
        }

        $request->session()->put(self::KEY, time());

        return $next($request);
    }
}
