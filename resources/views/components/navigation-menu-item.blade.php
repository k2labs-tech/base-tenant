<a href="{{ route($route) }}"
   wire:navigate.hover
    @class([
         'group flex items-center px-2 py-2 text-sm font-medium rounded-l-md',
         'bg-gray-200 text-gray-950 border-r-4 border-gray-700' => request()->routeIs($route),
         'text-gray-700 hover:bg-gray-50 hover:text-gray-950' => !request()->routeIs($route),
     ])>
    <x-dynamic-component
        :component="'heroicons::'.$icon"
        class="mr-3 shrink-0 h-6 w-6"
    />
    @lang($label)
</a>
