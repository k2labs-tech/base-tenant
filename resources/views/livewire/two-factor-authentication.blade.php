<div>
    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::auth.2fa.title') }}</flux:heading>
            <flux:subheading>
                @if ($user->hasTwoFactorEnabled())
                    {{ __('base-tenant::auth.2fa.status_enabled') }}
                @else
                    {{ __('base-tenant::auth.2fa.status_disabled') }}
                @endif
            </flux:subheading>
        </div>

        <div class="md:col-span-2 max-w-xl space-y-6">
            @if ($user->hasTwoFactorEnabled())
                <flux:button wire:click="openDisableModal" variant="danger" icon="shield-exclamation">
                    {{ __('base-tenant::auth.2fa.disable') }}
                </flux:button>

                <flux:separator />

                <div class="space-y-3">
                    <div>
                        <flux:heading size="sm">{{ __('base-tenant::auth.2fa.recovery_codes_title') }}</flux:heading>
                        <flux:subheading>{{ __('base-tenant::auth.2fa.recovery_codes_description') }}</flux:subheading>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button wire:click="$set('showRecoveryCodesModal', true)" size="sm">
                            {{ __('base-tenant::auth.2fa.view_recovery_codes') }}
                        </flux:button>

                        <flux:button wire:click="regenerateRecoveryCodes" size="sm">
                            {{ __('base-tenant::auth.2fa.regenerate_codes') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <flux:button wire:click="openEnableModal" variant="primary" icon="shield-check">
                    {{ __('base-tenant::auth.2fa.enable') }}
                </flux:button>
            @endif
        </div>
    </section>

    <flux:modal wire:model="showEnableModal" name="enable-2fa" class="min-w-[22rem] md:min-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('base-tenant::auth.2fa.setup_title') }}</flux:heading>

            <div class="space-y-3">
                <flux:text>{{ __('base-tenant::auth.2fa.setup_step1') }}</flux:text>

                <div class="flex justify-center">
                    {{-- El código QR es negro sobre transparente: este fondo
                         claro no lleva pareja oscura a propósito, porque
                         oscurecerlo dejaría el código sin contraste y sin poder
                         escanearse. --}}
                    <div class="rounded-lg border border-zinc-200 bg-white p-4">
                        {!! $qrCodeSvg !!}
                    </div>
                </div>

                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-950 p-3">
                    <flux:text size="sm">{{ __('base-tenant::auth.2fa.manual_entry') }}</flux:text>
                    <p class="mt-1 font-mono text-xs break-all text-zinc-700 dark:text-zinc-200">{{ $secret }}</p>
                </div>
            </div>

            <flux:input
                wire:model="confirmationCode"
                :label="__('base-tenant::auth.2fa.verification_code')"
                :description="__('base-tenant::auth.2fa.setup_step2')"
                placeholder="000000"
                inputmode="numeric"
                maxlength="6"
                autocomplete="off"
            />

            <flux:input
                wire:model="password"
                type="password"
                :label="__('base-tenant::auth.2fa.confirm_password')"
                autocomplete="current-password"
                viewable
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="enable" variant="primary">
                    <span wire:loading.remove wire:target="enable">{{ __('base-tenant::auth.2fa.enable') }}</span>
                    <span wire:loading wire:target="enable">{{ __('base-tenant::common.processing') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDisableModal" name="disable-2fa" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('base-tenant::auth.2fa.disable_confirmation_title') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::auth.2fa.disable_confirmation_description') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="password"
                type="password"
                :label="__('base-tenant::auth.2fa.confirm_password')"
                autocomplete="current-password"
                viewable
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="disable" variant="danger">
                    <span wire:loading.remove wire:target="disable">{{ __('base-tenant::auth.2fa.disable') }}</span>
                    <span wire:loading wire:target="disable">{{ __('base-tenant::common.processing') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showRecoveryCodesModal" name="recovery-codes" class="min-w-[22rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('base-tenant::auth.2fa.recovery_codes_title') }}</flux:heading>

            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>{{ __('base-tenant::auth.2fa.recovery_codes_warning') }}</flux:callout.text>
            </flux:callout>

            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-950 p-4">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($recoveryCodes as $code)
                        <div class="font-mono text-sm text-zinc-700 dark:text-zinc-200">{{ $code }}</div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-between gap-3">
                <flux:button wire:click="downloadRecoveryCodes" icon="arrow-down-tray">
                    {{ __('base-tenant::auth.2fa.download_codes') }}
                </flux:button>

                <flux:modal.close>
                    <flux:button variant="primary">{{ __('base-tenant::auth.2fa.codes_saved') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
