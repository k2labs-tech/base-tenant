<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers\Auth;

use Base\Tenant\Http\Controllers\Controller;
use Base\Tenant\Social\SocialAuthException;
use Base\Tenant\Social\SocialLoginService;
use Base\Tenant\Social\SocialProviders;
use Base\Tenant\Support\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * The two halves of an OAuth round trip.
 *
 * The same pair serves signing in and linking from the profile: which one is
 * happening is decided by whether there is a session, not by a second set of
 * routes that would have to be kept in step.
 */
class SocialLoginController extends Controller
{
    public function __construct(protected SocialLoginService $social) {}

    public function redirect(string $provider): SymfonyRedirect
    {
        $this->assertAvailable($provider);

        return Socialite::driver(SocialProviders::driver($provider))->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->assertAvailable($provider);

        try {
            $identity = Socialite::driver(SocialProviders::driver($provider))->user();
        } catch (Throwable) {
            // A cancelled consent screen comes back here too. Nothing has gone
            // wrong that the user needs the details of.
            return $this->back($provider, __('base-tenant::social.errors.cancelled'));
        }

        // Already signed in: this is a link from the profile, not a sign-in.
        if (Auth::check()) {
            try {
                $this->social->link(Auth::user(), $provider, $identity);
            } catch (SocialAuthException $exception) {
                return redirect()->route('base-tenant.profile')
                    ->with('error', $exception->getMessage());
            }

            return redirect()->route('base-tenant.profile')
                ->with('status', __('base-tenant::social.linked', [
                    'provider' => SocialProviders::label($provider),
                ]));
        }

        try {
            $user = $this->social->authenticate($provider, $identity);
        } catch (SocialAuthException $exception) {
            return $this->back($provider, $exception->getMessage());
        }

        // Second factor first. Signing the user in here and challenging
        // afterwards would mean the session already exists while the challenge
        // is pending, which is exactly what the second factor is for.
        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            Session::put([
                '2fa.user_id' => $user->getKey(),
                '2fa.remember' => false,
            ]);

            return redirect()->route('base-tenant.two-factor.challenge');
        }

        Auth::login($user, remember: true);

        Session::regenerate();

        return redirect()->intended(route('base-tenant.dashboard'));
    }

    protected function assertAvailable(string $provider): void
    {
        abort_unless(Module::enabled(Module::SOCIAL), 404);
        abort_unless(SocialProviders::configured($provider), 404);
    }

    protected function back(string $provider, string $message): RedirectResponse
    {
        return redirect()->route('base-tenant.login')->withErrors(['social' => $message]);
    }
}
