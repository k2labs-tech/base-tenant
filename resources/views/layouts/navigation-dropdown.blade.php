<div
    x-cloak
    @click.outside="userDropdownMenu = false"
    x-show="userDropdownMenu"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-lg bg-white py-1 shadow-soft ring-1 ring-surface-200 focus:outline-hidden"
    role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
    <!-- Active: "bg-gray-100", Not Active: "" -->
    @if(auth()->user()->account)
    <h4 class="block px-4 py-2 font-bold text-primary-900 uppercase">{{ Auth::user()->account->name }}</h4>
    @endif
    <hr>
    <h5 class="block px-4 py-2 text-xs font-semibold text-primary-400 uppercase tracking-wider">@lang('app.dropdown.title.profile')</h5>
    <x-dropdown-link :href="route('base-tenant.profile')">@lang('app.dropdown.link.profile')</x-dropdown-link>
    <x-dropdown-link :href="route('base-tenant.dashboard')">@lang('app.dropdown.link.alerts')</x-dropdown-link>
    <hr>
    @if(auth()->user()->hasRole('customer-admin'))
    <h5 class="block px-4 py-2 text-xs font-semibold text-primary-400 uppercase tracking-wider">@lang('app.dropdown.title.account')</h5>
        <x-dropdown-link :href="route('base-tenant.dashboard')">@lang('app.dropdown.link.settings')</x-dropdown-link>
        <x-dropdown-link :href="route('billing')">@lang('app.dropdown.link.billing')</x-dropdown-link>
    @endif
    <hr>
    <livewire:logout />

</div>
