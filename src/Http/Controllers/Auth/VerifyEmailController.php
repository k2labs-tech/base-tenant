<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers\Auth;

use Base\Tenant\Http\Controllers\Controller;
use Base\Tenant\Support\Home;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(Home::url(absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(Home::url(absolute: false).'?verified=1');
    }
}
