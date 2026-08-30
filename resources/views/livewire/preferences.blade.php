<div>
    <form wire:submit="updatePreferences" class="space-y-10">
        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::app.profile.regional') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::app.profile.regional_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="locale" :label="__('base-tenant::app.profile.language')">
                        @foreach($this->locales as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="timezone" :label="__('base-tenant::app.profile.timezone')">
                        @foreach($this->timezones as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="date_format" :label="__('base-tenant::app.profile.date_format')">
                        @foreach($this->dateFormats as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="time_format" :label="__('base-tenant::app.profile.time_format')">
                        @foreach($this->timeFormats as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </section>

        <flux:separator />

        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::app.profile.number_formatting') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::app.profile.number_formatting_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="currency" :label="__('base-tenant::app.profile.currency')">
                        @foreach($this->currencies as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="decimal_places" :label="__('base-tenant::app.profile.decimal_places')">
                        @for($i = 0; $i <= 4; $i++)
                            <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                        @endfor
                    </flux:select>

                    <flux:select wire:model="decimals_separator" :label="__('base-tenant::app.profile.decimals_separator')">
                        @foreach($this->separators as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="thousands_separator" :label="__('base-tenant::app.profile.thousands_separator')">
                        @foreach($this->separators as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </section>

        <flux:separator />

        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::app.profile.email_notifications') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::app.profile.email_notifications_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl">
                <flux:select
                    wire:model="daily_notification_summary"
                    :label="__('base-tenant::app.profile.daily_notification_summary')"
                    :description="__('base-tenant::app.profile.daily_notification_summary_description')"
                >
                    <flux:select.option value="">
                        {{ __('base-tenant::app.profile.use_account_default', [
                            'state' => (auth()->user()->account->daily_notification_summary ?? false)
                                ? __('base-tenant::app.profile.account_default_enabled')
                                : __('base-tenant::app.profile.account_default_disabled'),
                        ]) }}
                    </flux:select.option>
                    <flux:select.option value="1">{{ __('base-tenant::app.profile.always_enabled') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('base-tenant::app.profile.always_disabled') }}</flux:select.option>
                </flux:select>
            </div>
        </section>

        <flux:separator />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updatePreferences">{{ __('base-tenant::app.profile.save') }}</span>
                <span wire:loading wire:target="updatePreferences">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </div>
    </form>
</div>
