<div>
    <section>
        <header>
            <h2 class="text-lg font-medium text-primary-900">
                {{ __('base-tenant::auth.2fa.title') }}
            </h2>

            <p class="mt-1 text-sm text-primary-600">
                @if ($user->hasTwoFactorEnabled())
                    {{ __('base-tenant::auth.2fa.status_enabled') }}
                @else
                    {{ __('base-tenant::auth.2fa.status_disabled') }}
                @endif
            </p>
        </header>

        <div class="mt-6">
            @if ($user->hasTwoFactorEnabled())
                <x-danger-button wire:click="openDisableModal">
                    {{ __('base-tenant::auth.2fa.disable') }}
                </x-danger-button>
            @else
                <x-primary-button wire:click="openEnableModal">
                    {{ __('base-tenant::auth.2fa.enable') }}
                </x-primary-button>
            @endif
        </div>

        @if ($user->hasTwoFactorEnabled())
            <div class="mt-6 border-t pt-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">
                    {{ __('base-tenant::auth.2fa.recovery_codes_title') }}
                </h4>
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('base-tenant::auth.2fa.recovery_codes_description') }}
                </p>
                <div class="flex space-x-3">
                    <x-secondary-button wire:click="$set('showRecoveryCodesModal', true)">
                        {{ __('base-tenant::auth.2fa.view_recovery_codes') }}
                    </x-secondary-button>
                    <x-secondary-button wire:click="regenerateRecoveryCodes">
                        {{ __('base-tenant::auth.2fa.regenerate_codes') }}
                    </x-secondary-button>
                </div>
            </div>
        @endif
    </section>

    <!-- Enable 2FA Modal -->
    <x-modal name="enable-2fa" :show="$showEnableModal" focusable>
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                {{ __('base-tenant::auth.2fa.setup_title') }}
            </h3>

            <div class="space-y-6">
                <!-- Step 1: Scan QR Code -->
                <div>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('base-tenant::auth.2fa.setup_step1') }}
                    </p>
                    <div class="flex justify-center mb-4">
                        <div class="bg-white p-4 border rounded-lg">
                            {!! $qrCodeSvg !!}
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-600 mb-2">{{ __('base-tenant::auth.2fa.manual_entry') }}</p>
                        <p class="text-xs font-mono break-all">{{ $secret }}</p>
                    </div>
                </div>

                <!-- Step 2: Enter Verification Code -->
                <div>
                    <p class="text-sm text-gray-600 mb-3">
                        {{ __('base-tenant::auth.2fa.setup_step2') }}
                    </p>
                    <x-input-label for="confirmationCode" :value="__('base-tenant::auth.2fa.verification_code')" />
                    <x-text-input
                        id="confirmationCode"
                        wire:model="confirmationCode"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="000000"
                        maxlength="6"
                        autocomplete="off"
                    />
                    <x-input-error :messages="$errors->get('confirmationCode')" class="mt-2" />
                </div>

                <!-- Step 3: Confirm Password -->
                <div>
                    <x-input-label for="enable_password" :value="__('base-tenant::auth.2fa.confirm_password')" />
                    <x-text-input
                        id="enable_password"
                        wire:model="password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button wire:click="$set('showEnableModal', false)">
                    {{ __('base-tenant::common.cancel') }}
                </x-secondary-button>
                <x-primary-button wire:click="enable">
                    {{ __('base-tenant::auth.2fa.enable') }}
                </x-primary-button>
            </div>
        </div>
    </x-modal>

    <!-- Disable 2FA Modal -->
    <x-modal name="disable-2fa" :show="$showDisableModal" focusable>
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                {{ __('base-tenant::auth.2fa.disable_confirmation_title') }}
            </h3>

            <p class="text-sm text-gray-600 mb-6">
                {{ __('base-tenant::auth.2fa.disable_confirmation_description') }}
            </p>

            <x-input-label for="disable_password" :value="__('base-tenant::auth.2fa.confirm_password')" />
            <x-text-input
                id="disable_password"
                wire:model="password"
                type="password"
                class="mt-1 block w-full"
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button wire:click="$set('showDisableModal', false)">
                    {{ __('base-tenant::common.cancel') }}
                </x-secondary-button>
                <x-danger-button wire:click="disable">
                    {{ __('base-tenant::auth.2fa.disable') }}
                </x-danger-button>
            </div>
        </div>
    </x-modal>

    <!-- Recovery Codes Modal -->
    <x-modal name="recovery-codes" :show="$showRecoveryCodesModal" focusable>
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                {{ __('base-tenant::auth.2fa.recovery_codes_title') }}
            </h3>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <p class="text-sm text-yellow-800">
                    {{ __('base-tenant::auth.2fa.recovery_codes_warning') }}
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($recoveryCodes as $code)
                        <div class="font-mono text-sm">{{ $code }}</div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-between">
                <x-secondary-button wire:click="downloadRecoveryCodes">
                    {{ __('base-tenant::auth.2fa.download_codes') }}
                </x-secondary-button>
                <x-primary-button wire:click="$set('showRecoveryCodesModal', false)">
                    {{ __('base-tenant::auth.2fa.codes_saved') }}
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
