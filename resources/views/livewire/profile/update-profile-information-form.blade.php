<section class="grid gap-6 md:grid-cols-3">
    <div class="md:col-span-1">
        <flux:heading size="lg">{{ __('base-tenant::app.profile.information') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::app.profile.update_profile_information') }}</flux:subheading>
    </div>

    <form wire:submit="updateProfileInformation" class="md:col-span-2 max-w-xl space-y-4">
        <flux:input
            wire:model="name"
            :label="__('base-tenant::app.profile.name')"
            autocomplete="name"
            required
            autofocus
        />

        <flux:input
            wire:model="email"
            type="email"
            :label="__('base-tenant::app.profile.email')"
            autocomplete="username"
            required
        />

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>
                    {{ __('base-tenant::app.profile.email_unverified') }}
                </flux:callout.text>

                <x-slot:actions>
                    <flux:button wire:click.prevent="sendVerification" size="sm" variant="ghost">
                        {{ __('base-tenant::app.profile.resend_verification') }}
                    </flux:button>
                </x-slot:actions>
            </flux:callout>

            @if (session('status') === 'verification-link-sent')
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.text>
                        {{ __('base-tenant::app.profile.verification_sent') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        @endif

        <flux:button type="submit" variant="primary">
            <span wire:loading.remove wire:target="updateProfileInformation">{{ __('base-tenant::app.profile.save') }}</span>
            <span wire:loading wire:target="updateProfileInformation">{{ __('base-tenant::common.saving') }}</span>
        </flux:button>
    </form>
</section>
