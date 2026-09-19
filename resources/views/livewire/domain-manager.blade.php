<div class="space-y-5">
    <div class="space-y-1">
        <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
            {{ __('base-tenant::domains.title') }}
        </h1>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('base-tenant::domains.description') }}
        </p>
    </div>

    @if($currentUrl)
        <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50/60 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
            <flux:icon.globe-alt variant="micro" />
            {{ __('base-tenant::domains.currently_served_at') }}
            <span class="font-medium text-zinc-900 dark:text-white">{{ $currentUrl }}</span>
        </div>
    @endif

    {{-- El subdominio --}}
    @if($subdomainsEnabled)
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ __('base-tenant::domains.subdomain.title') }}
                </h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::domains.subdomain.description') }}
                </p>
            </div>

            <div class="space-y-3 px-4 py-4">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-56 flex-1">
                        <flux:input
                            wire:model.live.debounce.400ms="subdomain"
                            :label="__('base-tenant::domains.subdomain.label')"
                            :placeholder="__('base-tenant::domains.subdomain.placeholder')"
                            :disabled="! $canUpdate"
                            autocomplete="off"
                        >
                            @if($centralDomain)
                                <x-slot name="iconTrailing">
                                    <span class="pr-3 text-sm text-zinc-400 dark:text-zinc-500">.{{ $centralDomain }}</span>
                                </x-slot>
                            @endif
                        </flux:input>
                    </div>

                    @if($canUpdate)
                        <flux:button variant="primary" wire:click="saveSubdomain" wire:loading.attr="disabled">
                            {{ __('base-tenant::common.save') }}
                        </flux:button>
                    @endif
                </div>

                @error('subdomain')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::domains.subdomain.hint') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Los dominios propios --}}
    @if($customEnabled)
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ __('base-tenant::domains.custom.title') }}
                </h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::domains.custom.description') }}
                </p>
            </div>

            @if($canUpdate && ($maxDomains === 0 || $domains->count() < $maxDomains))
                <div class="space-y-2 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-56 flex-1">
                            <flux:input
                                wire:model="hostname"
                                :label="__('base-tenant::domains.custom.label')"
                                placeholder="app.micliente.com"
                                autocomplete="off"
                            />
                        </div>

                        <flux:button variant="primary" wire:click="addDomain" wire:loading.attr="disabled">
                            {{ __('base-tenant::domains.custom.add') }}
                        </flux:button>
                    </div>

                    @error('hostname')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if($domains->isEmpty())
                <div class="px-4 py-10 text-center">
                    <flux:icon.globe-alt class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ __('base-tenant::domains.custom.empty_title') }}
                    </p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::domains.custom.empty_description') }}
                    </p>
                </div>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($domains as $domain)
                        <li class="space-y-3 px-4 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $domain->hostname }}</span>

                                    @if($domain->is_primary)
                                        <flux:badge size="sm" color="zinc">{{ __('base-tenant::domains.primary') }}</flux:badge>
                                    @endif

                                    <flux:badge
                                        size="sm"
                                        :color="$domain->status === 'verified' ? 'green' : ($domain->status === 'failed' ? 'red' : 'amber')"
                                    >
                                        {{ __('base-tenant::domains.statuses.'.$domain->status) }}
                                    </flux:badge>
                                </div>

                                @if($canUpdate)
                                    <div class="flex items-center gap-2">
                                        @unless($domain->isVerified())
                                            <flux:button size="sm" wire:click="verifyDomain({{ $domain->id }})" wire:loading.attr="disabled">
                                                {{ __('base-tenant::domains.custom.verify') }}
                                            </flux:button>
                                        @endunless

                                        @if($domain->isVerified() && ! $domain->is_primary)
                                            <flux:button size="sm" variant="ghost" wire:click="makePrimary({{ $domain->id }})">
                                                {{ __('base-tenant::domains.custom.make_primary') }}
                                            </flux:button>
                                        @endif

                                        <flux:modal.trigger name="remove-domain-{{ $domain->id }}">
                                            <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('base-tenant::domains.custom.remove')" />
                                        </flux:modal.trigger>
                                    </div>
                                @endif
                            </div>

                            {{-- Mientras no esté verificado, lo único útil que
                                 se puede enseñar es exactamente qué registro
                                 falta por publicar. --}}
                            @unless($domain->isVerified())
                                @php($record = $domain->expectedRecord())
                                <div class="space-y-2 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-800/50">
                                    <p class="text-zinc-600 dark:text-zinc-300">
                                        {{ __('base-tenant::domains.custom.instructions') }}
                                    </p>

                                    <dl class="grid gap-1 font-mono text-zinc-700 dark:text-zinc-200">
                                        <div class="flex gap-2">
                                            <dt class="w-16 shrink-0 text-zinc-400 dark:text-zinc-500">{{ __('base-tenant::domains.record.type') }}</dt>
                                            <dd>{{ $record['type'] }}</dd>
                                        </div>
                                        <div class="flex gap-2">
                                            <dt class="w-16 shrink-0 text-zinc-400 dark:text-zinc-500">{{ __('base-tenant::domains.record.host') }}</dt>
                                            <dd class="break-all">{{ $record['host'] }}</dd>
                                        </div>
                                        <div class="flex gap-2">
                                            <dt class="w-16 shrink-0 text-zinc-400 dark:text-zinc-500">{{ __('base-tenant::domains.record.value') }}</dt>
                                            <dd class="break-all">{{ $record['value'] }}</dd>
                                        </div>
                                    </dl>

                                    @if($cnameTarget)
                                        <p class="text-zinc-600 dark:text-zinc-300">
                                            {{ __('base-tenant::domains.custom.cname_hint', ['target' => $cnameTarget]) }}
                                        </p>
                                    @endif

                                    @if($domain->last_error)
                                        <p class="text-danger-600 dark:text-danger-400">{{ $domain->last_error }}</p>
                                    @endif
                                </div>
                            @endunless

                            {{-- Confirmación destructiva en modal, que nombra
                                 el objeto: el diálogo nativo del navegador no
                                 se traduce y se puede silenciar. --}}
                            <flux:modal name="remove-domain-{{ $domain->id }}" class="md:w-96">
                                <div class="space-y-4">
                                    <flux:heading size="lg">{{ __('base-tenant::domains.custom.remove_title') }}</flux:heading>

                                    <flux:text>
                                        {{ __('base-tenant::domains.custom.remove_confirm', ['hostname' => $domain->hostname]) }}
                                    </flux:text>

                                    <div class="flex justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                                        </flux:modal.close>

                                        <flux:button variant="danger" wire:click="removeDomain({{ $domain->id }})">
                                            {{ __('base-tenant::domains.custom.remove') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
