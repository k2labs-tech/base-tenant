<div class="space-y-6">
    <flux:text>
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </flux:text>

    @if (session('status') == 'verification-link-sent')
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex items-center justify-between gap-4">
        <flux:button wire:click="sendVerification" variant="primary">
            <span wire:loading.remove wire:target="sendVerification">{{ __('Resend Verification Email') }}</span>
            <span wire:loading wire:target="sendVerification">{{ __('base-tenant::common.processing') }}</span>
        </flux:button>

        <flux:link wire:click="logout" as="button" variant="subtle" class="text-sm">
            {{ __('Log Out') }}
        </flux:link>
    </div>
</div>
