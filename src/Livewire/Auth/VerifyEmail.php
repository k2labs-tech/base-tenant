<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.guest')]
class VerifyEmail extends Component
{
    /**
     * Send verification email automatically on first visit.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('base-tenant.dashboard', absolute: false), navigate: true);
            return;
        }

        // Don't send if user still needs to change password (they'll come back after)
        if ($user->must_change_password ?? false) {
            return;
        }

        // Send verification email automatically if not sent recently
        $sessionKey = 'verification-email-sent-' . $user->id;
        if (!Session::has($sessionKey)) {
            $user->sendEmailVerificationNotification();
            Session::put($sessionKey, now());
            Session::flash('status', 'verification-link-sent');
        }
    }

    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('base-tenant.dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.verify-email');
    }
}
