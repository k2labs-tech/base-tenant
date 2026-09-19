<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('base-tenant::sessions.title') }}</flux:heading>
            <flux:subheading>
                {{ $isSelf ? __('base-tenant::sessions.description') : __('base-tenant::sessions.description_admin') }}
            </flux:subheading>
        </div>

        @if($sessions->count() > ($isSelf ? 1 : 0))
            <flux:modal.trigger name="revoke-other-sessions">
                <flux:button size="sm" variant="danger">
                    {{ $isSelf ? __('base-tenant::sessions.revoke_others') : __('base-tenant::sessions.revoke_all') }}
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    @if($sessions->isEmpty())
        <div class="rounded-xl border border-zinc-200 px-4 py-10 text-center dark:border-zinc-800">
            <flux:icon.computer-desktop class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
            <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                {{ __('base-tenant::sessions.empty_title') }}
            </p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::sessions.empty_description') }}
            </p>
        </div>
    @else
        <ul class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @foreach($sessions as $session)
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($session->device === 'mobile')
                            <flux:icon.device-phone-mobile class="size-5 text-zinc-400 dark:text-zinc-500" />
                        @elseif($session->device === 'tablet')
                            <flux:icon.device-tablet class="size-5 text-zinc-400 dark:text-zinc-500" />
                        @else
                            <flux:icon.computer-desktop class="size-5 text-zinc-400 dark:text-zinc-500" />
                        @endif

                        <div class="space-y-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $session->browser ?? __('base-tenant::sessions.unknown_browser') }}
                                    @if($session->platform)
                                        <span class="text-zinc-500 dark:text-zinc-400">· {{ $session->platform }}</span>
                                    @endif
                                </span>

                                @if($isSelf && $session->isCurrent())
                                    <flux:badge size="sm" color="green">{{ __('base-tenant::sessions.this_device') }}</flux:badge>
                                @endif
                            </div>

                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $session->ip_address }}
                                @if($session->last_active_at)
                                    · {{ __('base-tenant::sessions.last_active', ['when' => $session->last_active_at->diffForHumans()]) }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @unless($isSelf && $session->isCurrent())
                        <flux:modal.trigger name="revoke-session-{{ $session->id }}">
                            <flux:button size="sm" variant="ghost">
                                {{ __('base-tenant::sessions.revoke') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endunless

                    <flux:modal name="revoke-session-{{ $session->id }}" class="md:w-96">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('base-tenant::sessions.revoke_title') }}</flux:heading>

                            <flux:text>
                                {{ __('base-tenant::sessions.revoke_confirm', [
                                    'device' => trim(($session->browser ?? __('base-tenant::sessions.unknown_browser')).' · '.($session->platform ?? '')),
                                    'ip' => $session->ip_address,
                                ]) }}
                            </flux:text>

                            <flux:text class="text-xs">
                                {{ __('base-tenant::sessions.revoke_delay_notice') }}
                            </flux:text>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" wire:click="revoke({{ $session->id }})">
                                    {{ __('base-tenant::sessions.revoke') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="revoke-other-sessions" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">
                {{ $isSelf ? __('base-tenant::sessions.revoke_others') : __('base-tenant::sessions.revoke_all') }}
            </flux:heading>

            <flux:text>
                {{ $isSelf ? __('base-tenant::sessions.revoke_others_confirm') : __('base-tenant::sessions.revoke_all_confirm') }}
            </flux:text>

            <flux:text class="text-xs">
                {{ __('base-tenant::sessions.revoke_delay_notice') }}
            </flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="revokeOthers">
                    {{ __('base-tenant::sessions.revoke') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
