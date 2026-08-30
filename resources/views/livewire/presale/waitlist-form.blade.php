<div>
    @if($joined)
        <div class="flex items-center gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800 dark:border-success-900 dark:bg-success-950/40 dark:text-success-200">
            <flux:icon.check-circle variant="micro" />
            {{ __('base-tenant::presale.waitlist_thanks') }}
        </div>
    @else
        <form wire:submit="join" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex-1">
                <flux:input
                    wire:model="email"
                    type="email"
                    :placeholder="__('base-tenant::presale.waitlist_email')"
                    :label:sr-only="__('base-tenant::presale.waitlist_email')"
                    required
                />
            </div>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="join">{{ __('base-tenant::presale.waitlist_action') }}</span>
                <span wire:loading wire:target="join">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </form>
    @endif
</div>
