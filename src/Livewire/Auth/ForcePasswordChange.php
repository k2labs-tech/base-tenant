<?php

namespace Base\Tenant\Livewire\Auth;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('base-tenant::layouts.guest')]
class ForcePasswordChange extends Component
{
    public $current_password = '';

    public $password = '';

    public $password_confirmation = '';

    protected $rules = [
        'current_password' => 'required',
        'password' => 'required|string|min:8|confirmed|different:current_password',
    ];

    protected $messages = [
        'password.different' => 'The new password must be different from your current password.',
    ];

    public function mount()
    {
        // Ensure user is authenticated
        if (! Auth::check()) {
            return redirect()->route('base-tenant.login');
        }

        // If user doesn't need to change password, redirect to dashboard
        if (! Auth::user()->must_change_password) {
            return redirect()->route('base-tenant.dashboard');
        }
    }

    public function updatePassword()
    {
        $this->validate();

        $user = Auth::user();

        // Verify current password
        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', __('base-tenant::auth.current_password_incorrect'));

            return;
        }

        // Update password and remove flag
        $user->password = Hash::make($this->password);
        $user->must_change_password = false;
        $user->save();

        // Log the activity
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Changed password (forced)');

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::auth.password_changed'),
            text: __('base-tenant::auth.password_changed_success'),
        );

        // Redirect to dashboard
        return redirect()->route('base-tenant.dashboard');
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('base-tenant.login');
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.force-password-change');
    }
}
