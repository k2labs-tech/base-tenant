<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-center text-gray-900">
                {{ __('auth.2fa.verification_title') }}
            </h2>
            <p class="mt-2 text-sm text-center text-gray-600">
                @if (!$usingRecoveryCode)
                    {{ __('auth.2fa.verification_description') }}
                @else
                    {{ __('auth.2fa.recovery_description') }}
                @endif
            </p>
        </div>

        <form wire:submit="verify" class="space-y-6">
            @if (!$usingRecoveryCode)
                <div>
                    <x-input-label for="code" :value="__('auth.2fa.verification_code')" />
                    <x-text-input
                        wire:model="code"
                        id="code"
                        class="block mt-1 w-full"
                        type="text"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="000000"
                        required
                        autofocus
                        autocomplete="one-time-code"
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            @else
                <div>
                    <x-input-label for="recoveryCode" :value="__('auth.2fa.recovery_code')" />
                    <x-text-input
                        wire:model="recoveryCode"
                        id="recoveryCode"
                        class="block mt-1 w-full"
                        type="text"
                        required
                        autofocus
                    />
                    <x-input-error :messages="$errors->get('recoveryCode')" class="mt-2" />
                </div>
            @endif

            <div class="flex items-center justify-between">
                <button
                    type="button"
                    wire:click="toggleRecoveryCode"
                    class="text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer"
                >
                    @if (!$usingRecoveryCode)
                        {{ __('auth.2fa.use_recovery_code') }}
                    @else
                        {{ __('auth.2fa.use_authentication_code') }}
                    @endif
                </button>

                <x-primary-button class="ml-3">
                    {{ __('auth.2fa.verify') }}
                </x-primary-button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('base-tenant.login') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                {{ __('auth.2fa.back_to_login') }}
            </a>
        </div>
    </div>
</div>