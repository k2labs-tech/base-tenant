{{--
    `x-data` sin valor abre un árbol de Alpine: sin él, el `x-on:click` de los
    modos de apariencia no se procesaría si este parcial se incluyera fuera de
    la barra lateral (que sí trae su propio `x-data`).
--}}
<flux:dropdown position="top" align="start" class="w-full" x-data>
    <flux:sidebar.profile
        :name="Auth::user()->name"
        :initials="Auth::user()->initials"
        :chevron="true"
    />

    <flux:menu>
        @if (Auth::user()->account)
            <flux:menu.heading>{{ Auth::user()->account->name }}</flux:menu.heading>
        @endif

        <flux:menu.item icon="user" :href="route('base-tenant.profile')" wire:navigate>
            {{ __('base-tenant::app.dropdown.link.profile') }}
        </flux:menu.item>

        @can('accounts.update')
            <flux:menu.item icon="cog-6-tooth" :href="route('base-tenant.settings.index')" wire:navigate>
                {{ __('base-tenant::app.dropdown.link.settings') }}
            </flux:menu.item>
        @endcan

        <flux:menu.separator />

        {{--
            `$flux.appearance` es la propiedad reactiva que Flux expone como
            magia de Alpine; la respalda `localStorage['flux.appearance']` y
            admite `light`, `dark` y `system`.
        --}}
        <flux:menu.submenu icon="swatch" :heading="__('base-tenant::app.appearance.label')">
            <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">
                {{ __('base-tenant::app.appearance.light') }}
            </flux:menu.item>

            <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">
                {{ __('base-tenant::app.appearance.dark') }}
            </flux:menu.item>

            <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">
                {{ __('base-tenant::app.appearance.system') }}
            </flux:menu.item>
        </flux:menu.submenu>

        <flux:menu.separator />

        <livewire:base-tenant.logout />
    </flux:menu>
</flux:dropdown>
