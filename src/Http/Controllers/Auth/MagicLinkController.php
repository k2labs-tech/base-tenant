<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers\Auth;

use Base\Tenant\Facades\MagicLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Spends a sign-in link, in two steps on purpose.
 *
 * The link from the email is a GET, and a GET is something mail scanners,
 * link previewers and antivirus proxies follow before the person ever clicks.
 * A link spent on that GET is a link that never works for the human -- and,
 * for a user without a second factor, a session handed to the scanner. So the
 * GET only shows a button, and the token is spent by the POST behind it.
 */
class MagicLinkController
{
    /**
     * The page the email lands on. A spent or expired link renders here too,
     * with the reason and a way to ask for another: the login screen is a
     * Livewire component and would not show a flashed error.
     */
    public function show(string $token): View
    {
        abort_unless(MagicLink::enabled(), 404);

        return view('base-tenant::magic-link', [
            'token' => $token,
            'invalid' => ! MagicLink::isUsable($token),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        abort_unless(MagicLink::enabled(), 404);

        $user = MagicLink::consume($token);

        if ($user === null) {
            return redirect()->route('base-tenant.magic-link.show', ['token' => $token]);
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
