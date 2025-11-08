<div
    x-data="{ userDropdownMenu: false }"
    class="sticky top-0 z-10 flex h-10 shrink-0 bg-white">

    <button type="button"
            @click="mobileMenuVar = !mobileMenuVar"
            class="border-r border-gray-200 px-4 shadow text-gray-500 focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden">
        <span class="sr-only">Open sidebar</span>
        <x-heroicons::outline.bars-3-bottom-left class="h-6 w-6"/>
    </button>
    <div class="flex flex-1 justify-between px-4 shadow">
        <div class="flex-1 inline-flex items-center uppercase">
            {{--
            TODO: Add a logo here, visible just on mobile
            --}}
            <x-application-logo class="h-8 w-auto sm:h-10" />
        </div>
        <div
            class="ml-4 items-center md:ml-6 inline-flex">
            <div class="relative ml-3">
                <div class="inline-flex capitalize text-sm">
                    {{ auth()->user()->name }}
                </div>
                <div class="inline-flex ml-2">

                    <button type="button"
                            @click="userDropdownMenu = !userDropdownMenu"
                            class="flex max-w-xs items-center rounded-full bg-sky-900 hover:bg-sky-700 text-white text-sm focus:outline-hidden"
                            id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                        <x-base-tenant::avatar sm label="{{ auth()->user()->initials }}" border="none" class="bg-sky-900 hover:bg-sky-700 text-white" />
                    </button>
                </div>

                @include('layouts.navigation-dropdown')
            </div>
        </div>
    </div>
</div>
