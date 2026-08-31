<div class="space-y-5">
    <div class="space-y-1">
        <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
            {{ __('base-tenant::security.title') }}
        </h1>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('base-tenant::security.description') }}
        </p>
    </div>

    {{-- Doble factor --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::security.two_factor.title') }}
            </h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::security.two_factor.description') }}
            </p>
        </div>

        <div class="space-y-4 px-4 py-4">
            <flux:switch
                wire:model.live="requireTwoFactor"
                :label="__('base-tenant::security.two_factor.require')"
                :disabled="! $canUpdate"
            />

            @if($requireTwoFactor)
                <div class="max-w-xs">
                    <flux:input
                        type="number"
                        min="0"
                        wire:model="twoFactorGraceHours"
                        :label="__('base-tenant::security.two_factor.grace_hours')"
                        :description="__('base-tenant::security.two_factor.grace_hint')"
                        :disabled="! $canUpdate"
                    />
                </div>

                {{-- La cifra antes de guardar: es la diferencia entre una
                     decisión tomada y una sorpresa. --}}
                @if($membersWithoutTwoFactor > 0)
                    <div class="flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
                        <flux:icon.exclamation-triangle variant="micro" class="mt-0.5 shrink-0" />
                        <span>
                            {{ trans_choice('base-tenant::security.two_factor.affected', $membersWithoutTwoFactor, ['count' => $membersWithoutTwoFactor]) }}
                        </span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Dominios de email --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::security.email_domains.title') }}
            </h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::security.email_domains.description') }}
            </p>
        </div>

        <div class="space-y-2 px-4 py-4">
            <flux:textarea
                wire:model="allowedEmailDomains"
                rows="3"
                :label="__('base-tenant::security.email_domains.label')"
                :placeholder="'micliente.com'"
                :description="__('base-tenant::security.email_domains.hint')"
                :disabled="! $canUpdate"
            />

            @error('allowedEmailDomains')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Lista blanca de IP --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::security.ip.title') }}
            </h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::security.ip.description') }}
            </p>
        </div>

        <div class="space-y-4 px-4 py-4">
            <flux:radio.group wire:model.live="ipMode" :label="__('base-tenant::security.ip.mode')" :disabled="! $canUpdate">
                <flux:radio value="off" :label="__('base-tenant::security.ip.modes.off')" :description="__('base-tenant::security.ip.modes.off_hint')" />
                <flux:radio value="warn" :label="__('base-tenant::security.ip.modes.warn')" :description="__('base-tenant::security.ip.modes.warn_hint')" />
                <flux:radio value="enforce" :label="__('base-tenant::security.ip.modes.enforce')" :description="__('base-tenant::security.ip.modes.enforce_hint')" />
            </flux:radio.group>

            @if($ipMode !== 'off')
                <div class="space-y-2">
                    <flux:textarea
                        wire:model="ipAllowlist"
                        rows="4"
                        :label="__('base-tenant::security.ip.label')"
                        placeholder="203.0.113.4&#10;198.51.100.0/24"
                        :description="__('base-tenant::security.ip.hint')"
                        :disabled="! $canUpdate"
                    />

                    @error('ipAllowlist')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror

                    @if($canUpdate)
                        <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('base-tenant::security.ip.your_address', ['ip' => $currentIp]) }}</span>
                            <flux:button size="xs" variant="ghost" wire:click="addCurrentIp">
                                {{ __('base-tenant::security.ip.add_mine') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Sesión --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::security.session.title') }}
            </h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::security.session.description') }}
            </p>
        </div>

        <div class="max-w-xs px-4 py-4">
            <flux:input
                type="number"
                min="0"
                wire:model="sessionTimeoutMinutes"
                :label="__('base-tenant::security.session.timeout')"
                :description="__('base-tenant::security.session.timeout_hint')"
                :disabled="! $canUpdate"
            />
        </div>
    </div>

    @if($canUpdate)
        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                {{ __('base-tenant::common.save') }}
            </flux:button>
        </div>
    @endif
</div>
