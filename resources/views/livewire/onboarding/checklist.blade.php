<div>
    @if($visible)
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4 border-b border-zinc-200 bg-zinc-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                <div class="space-y-1">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ __('base-tenant::onboarding.title') }}
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::onboarding.description') }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium tabular-nums text-zinc-600 dark:text-zinc-300">{{ $progress }}%</span>

                    <flux:button
                        size="xs"
                        variant="ghost"
                        icon="x-mark"
                        wire:click="dismiss"
                        :aria-label="__('base-tenant::onboarding.dismiss')"
                    />
                </div>
            </div>

            <div class="h-1 w-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full bg-accent-500 transition-all" style="width: {{ $progress }}%"></div>
            </div>

            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($steps as $entry)
                    @php($step = $entry['step'])

                    <li wire:key="onboarding-{{ $step->key }}" class="flex items-center justify-between gap-4 px-4 py-3" data-onboarding-state="{{ $entry['complete'] ? 'complete' : 'pending' }}">
                        <div class="flex items-center gap-3">
                            @if($entry['complete'])
                                <flux:icon.check-circle variant="micro" class="text-success-500" />
                            @else
                                <span class="size-4 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                            @endif

                            <div>
                                <div @class([
                                    'text-sm',
                                    'font-medium text-zinc-900 dark:text-white' => ! $entry['complete'],
                                    'text-zinc-500 line-through dark:text-zinc-400' => $entry['complete'],
                                ])>
                                    {{ __($step->label) }}
                                </div>

                                @if($step->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __($step->description) }}</div>
                                @endif
                            </div>
                        </div>

                        @if(! $entry['complete'] && $step->url())
                            <flux:button size="xs" variant="filled" href="{{ $step->url() }}" wire:navigate>
                                {{ __('base-tenant::onboarding.go') }}
                            </flux:button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
