<div>
    <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('auth.2fa.title') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($user->hasTwoFactorEnabled())
                            {{ __('auth.2fa.status_enabled') }}
                        @else
                            {{ __('auth.2fa.status_disabled') }}
                        @endif
                    </p>
                </div>
                @if ($user->hasTwoFactorEnabled())
                    <x-button negative wire:click="openDisableModal">
                        {{ __('auth.2fa.disable') }}
                    </x-button>
                @else
                    <x-button primary wire:click="openEnableModal">
                        {{ __('auth.2fa.enable') }}
                    </x-button>
                @endif
            </div>

            @if ($user->hasTwoFactorEnabled())
                <div class="mt-6 border-t pt-6">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">
                        {{ __('auth.2fa.recovery_codes_title') }}
                    </h4>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('auth.2fa.recovery_codes_description') }}
                    </p>
                    <div class="flex space-x-3">
                        <x-button outline wire:click="$set('showRecoveryCodesModal', true)">
                            {{ __('auth.2fa.view_recovery_codes') }}
                        </x-button>
                        <x-button outline wire:click="regenerateRecoveryCodes">
                            {{ __('auth.2fa.regenerate_codes') }}
                        </x-button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Enable 2FA Modal -->
    <x-modal wire:model="showEnableModal" max-width="lg">
        <x-card>
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ __('auth.2fa.setup_title') }}
                </h3>

                <div class="space-y-6">
                    <!-- Step 1: Scan QR Code -->
                    <div>
                        <p class="text-sm text-gray-600 mb-4">
                            {{ __('auth.2fa.setup_step1') }}
                        </p>
                        <div class="flex justify-center mb-4">
                            <div class="bg-white p-4 border rounded-lg">
                                {!! $qrCodeSvg !!}
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-600 mb-2">{{ __('auth.2fa.manual_entry') }}</p>
                            <p class="text-xs font-mono break-all">{{ $secret }}</p>
                        </div>
                    </div>

                    <!-- Step 2: Enter Verification Code -->
                    <div>
                        <p class="text-sm text-gray-600 mb-3">
                            {{ __('auth.2fa.setup_step2') }}
                        </p>
                        <x-input
                            label="{{ __('auth.2fa.verification_code') }}"
                            wire:model="confirmationCode"
                            placeholder="000000"
                            maxlength="6"
                            autocomplete="off"
                        />
                    </div>

                    <!-- Step 3: Confirm Password -->
                    <div>
                        <x-password
                            label="{{ __('auth.2fa.confirm_password') }}"
                            wire:model="password"
                            autocomplete="current-password"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <x-button flat wire:click="$set('showEnableModal', false)">
                        {{ __('common.cancel') }}
                    </x-button>
                    <x-button primary wire:click="enable">
                        {{ __('auth.2fa.enable') }}
                    </x-button>
                </div>
            </div>
        </x-card>
    </x-modal>

    <!-- Disable 2FA Modal -->
    <x-modal wire:model="showDisableModal">
        <x-card>
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ __('auth.2fa.disable_confirmation_title') }}
                </h3>

                <p class="text-sm text-gray-600 mb-6">
                    {{ __('auth.2fa.disable_confirmation_description') }}
                </p>

                <x-password
                    label="{{ __('auth.2fa.confirm_password') }}"
                    wire:model="password"
                    autocomplete="current-password"
                />

                <div class="mt-6 flex justify-end space-x-3">
                    <x-button flat wire:click="$set('showDisableModal', false)">
                        {{ __('common.cancel') }}
                    </x-button>
                    <x-button negative wire:click="disable">
                        {{ __('auth.2fa.disable') }}
                    </x-button>
                </div>
            </div>
        </x-card>
    </x-modal>

    <!-- Recovery Codes Modal -->
    <x-modal wire:model="showRecoveryCodesModal" max-width="md">
        <x-card>
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ __('auth.2fa.recovery_codes_title') }}
                </h3>

                <x-alert warning class="mb-4">
                    {{ __('auth.2fa.recovery_codes_warning') }}
                </x-alert>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($recoveryCodes as $code)
                            <div class="font-mono text-sm">{{ $code }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-between">
                    <x-button outline wire:click="downloadRecoveryCodes">
                        {{ __('auth.2fa.download_codes') }}
                    </x-button>
                    <x-button primary wire:click="$set('showRecoveryCodesModal', false)">
                        {{ __('auth.2fa.codes_saved') }}
                    </x-button>
                </div>
            </div>
        </x-card>
    </x-modal>
</div>
