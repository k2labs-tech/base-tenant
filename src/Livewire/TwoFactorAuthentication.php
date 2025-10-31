<?php

namespace Base\Tenant\Livewire;

use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
//use WireUi\Traits\WireUiActions;

class TwoFactorAuthentication extends Component
{
//    use WireUiActions;

    public $showEnableModal = false;
    public $showDisableModal = false;
    public $showRecoveryCodesModal = false;
    public $confirmationCode = '';
    public $password = '';
    public $qrCodeSvg = '';
    public $secret = '';
    public $recoveryCodes = [];

    protected $rules = [
        'confirmationCode' => 'required|string|size:6',
        'password' => 'required|string',
    ];

    public function mount()
    {
        if (auth()->user()->hasTwoFactorEnabled()) {
            $this->recoveryCodes = auth()->user()->two_factor_recovery_codes;
        }
    }

    public function render()
    {
        return view('livewire.two-factor-authentication', [
            'user' => auth()->user(),
        ]);
    }

    public function openEnableModal()
    {
        $this->reset(['confirmationCode', 'password']);
        $this->generateSecret();
        $this->showEnableModal = true;
    }

    public function openDisableModal()
    {
        $this->reset(['password']);
        $this->showDisableModal = true;
    }

    public function generateSecret()
    {
        $google2fa = new Google2FA();
        $this->secret = $google2fa->generateSecretKey();

        // Generate QR code
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            auth()->user()->email,
            $this->secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $this->qrCodeSvg = $writer->writeString($qrCodeUrl);
    }

    public function enable()
    {
        $this->validate([
            'confirmationCode' => 'required|string|size:6',
            'password' => 'required|string',
        ]);

        // Verify password
        if (!auth()->validate([
            'email' => auth()->user()->email,
            'password' => $this->password,
        ])) {
            $this->addError('password', __('auth.password'));
            return;
        }

        // Verify the code
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($this->secret, $this->confirmationCode);

        if (!$valid) {
            $this->addError('confirmationCode', __('auth.2fa.invalid_code'));
            return;
        }

        // Enable 2FA for the user
        $user = auth()->user();
        $user->enableTwoFactorAuthentication($this->secret);
        $user->confirmTwoFactorAuthentication();

        $this->recoveryCodes = $user->two_factor_recovery_codes;
        $this->showEnableModal = false;
        $this->showRecoveryCodesModal = true;

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('auth.2fa.enabled_title'),
            'description' => __('auth.2fa.enabled_description'),
        ]);
    }

    public function disable()
    {
        $this->validate([
            'password' => 'required|string',
        ]);

        // Verify password
        if (!auth()->validate([
            'email' => auth()->user()->email,
            'password' => $this->password,
        ])) {
            $this->addError('password', __('auth.password'));
            return;
        }

        // Disable 2FA
        auth()->user()->disableTwoFactorAuthentication();

        $this->showDisableModal = false;
        $this->reset(['password', 'recoveryCodes']);

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('auth.2fa.disabled_title'),
            'description' => __('auth.2fa.disabled_description'),
        ]);
    }

    public function regenerateRecoveryCodes()
    {
        auth()->user()->regenerateRecoveryCodes();
        $this->recoveryCodes = auth()->user()->two_factor_recovery_codes;

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('auth.2fa.recovery_codes_regenerated'),
        ]);
    }

    public function downloadRecoveryCodes()
    {
        $codes = implode("\n", $this->recoveryCodes);
        $filename = 'recovery-codes-' . date('Y-m-d') . '.txt';

        return response()->streamDownload(function () use ($codes) {
            echo $codes;
        }, $filename);
    }
}
