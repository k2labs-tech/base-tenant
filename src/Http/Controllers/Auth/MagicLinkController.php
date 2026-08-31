<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers\Auth;

use Base\Tenant\Facades\MagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Spends a sign-in link.
 */
class MagicLinkController
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        abort_unless(MagicLink::enabled(), 404);

        $user = MagicLink::consume($token);

        if ($user === null) {
            return redirect()
                ->route('base-tenant.login')
                ->withErrors(['email' => __('base-tenant::passwordless.link_invalid')]);
        }

        // THE guard. A magic link is a way of proving you hold the mailbox --
        // it is not a way of proving the second factor. Signing somebody
        // straight in here would turn every account with 2FA into an account
        // whose 2FA can be skipped by anybody who reaches the inbox, which is
        // exactly the attack the second factor exists to stop.
        if ($user->hasTwoFactorEnabled()) {
            Session::put([
                '2fa.user_id' => $user->getKey(),
                '2fa.remember' => false,
            ]);

            return redirect()->route('base-tenant.two-factor.challenge');
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route(config('base-tenant.home_url', 'base-tenant.dashboard')));
    }
}
