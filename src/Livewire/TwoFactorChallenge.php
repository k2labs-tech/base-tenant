<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Livewire\Attributes\GuestLayout;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Home;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

/**
 * Sin este atributo Livewire cae en el layout por defecto de la aplicación, que
 * este paquete no trae: la pantalla a la que redirige el acceso cuando hay
 * segundo factor respondía con un 500 y dejaba el inicio de sesión sin salida.
 * El layout sale de `layouts.guest`; el `->layout()` que había en `render()`
 * nunca se aplicaba, porque el atributo de clase lo pisa.
 */
#[GuestLayout]
class TwoFactorChallenge extends Component
{
    public $code = '';

    public $recoveryCode = '';

    public $usingRecoveryCode = false;

    /**
     * Who is being challenged, taken from the session the password step left.
     * Locked: otherwise the browser could swap in somebody else and skip
     * their password with nothing but one of their codes.
     */
    #[Locked]
    public $userId;

    protected $rules = [
        'code' => 'required_unless:usingRecoveryCode,true|string|size:6',
        'recoveryCode' => 'required_if:usingRecoveryCode,true|string',
    ];

    public function mount($userId = null)
    {
        $this->userId = $userId ?? session('2fa.user_id');

        if (! $this->userId) {
            return redirect()->route('base-tenant.login');
        }
    }

    public function render()
    {
        return view('base-tenant::livewire.two-factor-challenge');
    }

    public function toggleRecoveryCode()
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
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

        $user = tenant_user_model()::find($this->userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => __('base-tenant::auth.failed'),
            ]);
        }

        $google2fa = new Google2FA;
        $valid = $google2fa->verifyKey($user->two_factor_secret, $this->code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => __('base-tenant::auth.2fa.invalid_code'),
            ]);
        }

        // Login the user
        Auth::login($user, session('2fa.remember', false));
        session()->forget(['2fa.user_id', '2fa.remember']);

        $this->redirect(Home::url());
    }

    protected function verifyRecoveryCode()
    {
        $this->validate([
            'recoveryCode' => 'required|string',
        ]);

        $user = tenant_user_model()::find($this->userId);

        if (! $user) {
            throw ValidationException::withMessages([
                'recoveryCode' => __('base-tenant::auth.failed'),
            ]);
        }

        $validCode = $user->invalidateRecoveryCode($this->recoveryCode);

        if (! $validCode) {
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

        $this->redirect(Home::url());
    }
}
