<?php

namespace Base\Tenant\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallenge extends Component
{

    public $code = '';
    public $recoveryCode = '';
    public $usingRecoveryCode = false;
    public $userId;

    protected $rules = [
        'code' => 'required_unless:usingRecoveryCode,true|string|size:6',
        'recoveryCode' => 'required_if:usingRecoveryCode,true|string',
    ];

    public function mount($userId = null)
    {
        $this->userId = $userId ?? session('2fa.user_id');

        if (!$this->userId) {
            return redirect()->route('login');
        }
    }

    public function render()
    {
        return view('base-tenant::livewire.two-factor-challenge')
            ->layout('base-tenant::guest-layout');
    }

    public function toggleRecoveryCode()
    {
        $this->usingRecoveryCode = !$this->usingRecoveryCode;
        $this->reset(['code', 'recoveryCode']);
        $this->resetValidation();
    }

    public function verify()
    {
        if ($this->usingRecoveryCode) {
            $this->verifyRecoveryCode();
        } else {
            $this->verifyTotpCode();
        }
    }

    protected function verifyTotpCode()
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = \App\Models\User::find($this->userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => __('base-tenant::auth.failed'),
            ]);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $this->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => __('base-tenant::auth.2fa.invalid_code'),
            ]);
        }

        // Login the user
        Auth::login($user, session('2fa.remember', false));
        session()->forget(['2fa.user_id', '2fa.remember']);

        $this->redirect(route('dashboard'));
    }

    protected function verifyRecoveryCode()
    {
        $this->validate([
            'recoveryCode' => 'required|string',
        ]);

        $user = \App\Models\User::find($this->userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'recoveryCode' => __('base-tenant::auth.failed'),
            ]);
        }

        $validCode = $user->invalidateRecoveryCode($this->recoveryCode);

        if (!$validCode) {
            throw ValidationException::withMessages([
                'recoveryCode' => __('base-tenant::auth.2fa.invalid_recovery_code'),
            ]);
        }

        // Login the user
        Auth::login($user, session('2fa.remember', false));
        session()->forget(['2fa.user_id', '2fa.remember']);

        Flux::toast(
            variant: 'warning',
            heading: __('base-tenant::auth.2fa.recovery_code_used'),
            text: __('base-tenant::auth.2fa.recovery_code_warning'),
        );

        $this->redirect(route('dashboard'));
    }
}
