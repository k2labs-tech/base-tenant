<div
    x-cloak
    @click.outside="languageSelector = false"
    x-show="languageSelector"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    class="absolute right-0 z-10 mt-2 min-w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden"
    role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">

    <h5 class="block px-4 py-2 text-sm font-bold text-gray-700 uppercase">@lang('app.languages')</h5>
    @foreach(config('custom.langs') as $key => $lang)
        <x-dropdown-link :href="route('base-tenant.profile')">{{ $lang }}</x-dropdown-link>
    @endforeach
</div>
