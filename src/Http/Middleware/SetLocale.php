<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Language;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Put the request into the right language.
     *
     * The preference is taken from the user, then the session, then the
     * default -- but each one is checked against the languages that are
     * actually enabled. Someone whose language was switched off yesterday
     * keeps a preference pointing at a locale the application no longer
     * offers, and honouring it would show them a half-translated interface
     * with no way back.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preferred = auth()->check() ? auth()->user()->locale : null;
        $preferred ??= $request->session()?->get('locale');

        $locale = $this->resolve($preferred);

        app()->setLocale($locale);

        // The fallback is the default language and not whatever `app.php`
        // says, or a string missing from the active locale would fall through
        // to a language nobody enabled.
        app()->setFallbackLocale($this->fallback());

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    protected function resolve(?string $preferred): string
    {
        if (! Module::enabled(Module::LANGUAGES)) {
            return $preferred ?? (string) config('app.locale');
        }

        return Language::resolve($preferred);
    }

    protected function fallback(): string
    {
        return Module::enabled(Module::LANGUAGES)
            ? Language::default()
            : (string) config('app.fallback_locale', 'en');
    }
}
