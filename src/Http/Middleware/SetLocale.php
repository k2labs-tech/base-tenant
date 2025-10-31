<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // First check if user is authenticated and has a locale preference
        if (auth()->check() && auth()->user()->locale) {
            app()->setLocale(auth()->user()->locale);
            session(['locale' => auth()->user()->locale]);
        }
        // Fall back to session locale
        elseif (session()->has('locale')) {
            app()->setLocale(session('locale'));
        }

        return $next($request);
    }
}
