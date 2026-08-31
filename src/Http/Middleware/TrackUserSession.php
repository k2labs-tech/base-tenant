<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Sessions;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the session list current, and act on a session somebody has ended.
 *
 * Revocation lands here rather than in the session driver so that it works
 * whatever driver the host chose. The trade-off is stated plainly in the
 * migration and in the docs: a revoked session ends on its next request, not
 * the instant the button is pressed.
 */
class TrackUserSession
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Module::enabled(Module::SECURITY)
            || ! config('base-tenant.security.sessions.enabled', true)
            || ! auth()->check()) {
            return $next($request);
        }

        if (Sessions::isRevoked($request)) {
            auth()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('base-tenant::sessions.revoked_notice'),
                ], 401);
            }

            return redirect()
                ->route('base-tenant.login')
                ->with('status', __('base-tenant::sessions.revoked_notice'));
        }

        Sessions::touch($request, auth()->user());

        return $next($request);
    }
}
