<div class="space-y-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('base-tenant::users.edit_user_title', ['name' => $user->name]) }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.edit_user_description') }}</flux:subheading>
        </div>

        <flux:button :href="route('base-tenant.users.index')" variant="ghost" icon="arrow-left">
            {{ __('base-tenant::users.back_to_users') }}
        </flux:button>
    </div>

    <flux:separator />

    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::users.profile_information') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.profile_information_description') }}</flux:subheading>
        </div>

        <form wire:submit="updateProfileInformation" class="md:col-span-2 max-w-xl space-y-4">
            <flux:input wire:model="name" :label="__('base-tenant::users.name')" required />
            <flux:input wire:model="email" type="email" :label="__('base-tenant::users.email')" required />
            <flux:input wire:model="phone" :label="__('base-tenant::users.phone')" />

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updateProfileInformation">{{ __('base-tenant::users.save_profile') }}</span>
                <span wire:loading wire:target="updateProfileInformation">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </form>
    </section>

    <flux:separator />

    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::users.update_password') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.update_password_description') }}</flux:subheading>
        </div>

        <form wire:submit="updatePassword" class="md:col-span-2 max-w-xl space-y-4">
            <flux:input wire:model="password" type="password" :label="__('base-tenant::users.new_password')" viewable />
            <flux:input wire:model="password_confirmation" type="password" :label="__('base-tenant::users.confirm_password')" viewable />

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updatePassword">{{ __('base-tenant::users.update_password') }}</span>
                <span wire:loading wire:target="updatePassword">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </form>
    </section>

    <flux:separator />

    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::users.preferences') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.preferences_description') }}</flux:subheading>
        </div>

        <form wire:submit="updatePreferences" class="md:col-span-2 max-w-xl space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="timezone" :label="__('base-tenant::users.timezone')" :placeholder="__('base-tenant::users.select_timezone')">
                    @foreach($timezones as $tz)
                        <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="locale" :label="__('base-tenant::users.language')" :placeholder="__('base-tenant::users.select_language')">
                    @foreach($locales as $code => $nombre)
                        <flux:select.option value="{{ $code }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="currency" :label="__('base-tenant::users.currency')" :placeholder="__('base-tenant::users.select_currency')">
                    @foreach($currencies as $code => $nombre)
                        <flux:select.option value="{{ $code }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="decimal_places" :label="__('base-tenant::users.decimal_places')" :placeholder="__('base-tenant::users.select_decimal_places')">
                    @for($i = 0; $i <= 4; $i++)
                        <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                    @endfor
                </flux:select>

                <flux:select wire:model="decimals_separator" :label="__('base-tenant::users.decimal_separator')" :placeholder="__('base-tenant::users.select_separator')">
                    <flux:select.option value=".">. ({{ __('base-tenant::users.dot') }})</flux:select.option>
                    <flux:select.option value=",">, ({{ __('base-tenant::users.comma') }})</flux:select.option>
                </flux:select>

                <flux:select wire:model="thousands_separator" :label="__('base-tenant::users.thousands_separator')" :placeholder="__('base-tenant::users.select_separator')">
                    <flux:select.option value=",">, ({{ __('base-tenant::users.comma') }})</flux:select.option>
                    <flux:select.option value=".">. ({{ __('base-tenant::users.dot') }})</flux:select.option>
                    <flux:select.option value=" ">{{ __('base-tenant::users.space') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="date_format" :label="__('base-tenant::users.date_format')" :placeholder="__('base-tenant::users.select_format')">
                    @foreach($dateFormats as $format => $example)
                        <flux:select.option value="{{ $format }}">{{ $example }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="time_format" :label="__('base-tenant::users.time_format')" :placeholder="__('base-tenant::users.select_format')">
                    @foreach($timeFormats as $format => $example)
                        <flux:select.option value="{{ $format }}">{{ $example }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updatePreferences">{{ __('base-tenant::users.save_preferences') }}</span>
                <span wire:loading wire:target="updatePreferences">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </form>
    </section>

    <flux:separator />

    <section class="grid gap-6 md:grid-cols-3">
        <div class="md:col-span-1">
            <flux:heading size="lg">{{ __('base-tenant::users.user_roles') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::users.user_roles_description') }}</flux:subheading>
        </div>

        <form wire:submit="updateRoles" class="md:col-span-2 max-w-xl space-y-4">
            @if($roles->isEmpty())
                <flux:text>{{ __('base-tenant::users.no_roles_available') }}</flux:text>
            @else
                <flux:checkbox.group name="selectedRoles">
                    @foreach($roles as $role)
                        <flux:checkbox
                            wire:model="selectedRoles"
                            value="{{ $role->id }}"
                            :label="$role->label"
                            :description="$role->description"
                        />
                    @endforeach
                </flux:checkbox.group>
            @endif

            {{-- El grupo no lleva etiqueta, así que Flux no monta su campo ni,
                 con él, el hueco del error: hay que pedirlo a mano. --}}
            <flux:error name="selectedRoles" />


            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updateRoles">{{ __('base-tenant::users.update_roles') }}</span>
                <span wire:loading wire:target="updateRoles">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </form>
    </section>
</div>
