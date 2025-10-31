<div
x-cloak
x-show="mobileMenuVar"
class="relative z-40 md:hidden shadow" role="dialog" aria-modal="true">

    <div
        x-show="mobileMenuVar"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"

        class="fixed inset-0 bg-gray-600/75"></div>

    <div class="fixed inset-0 z-40 flex">

        <div
            @click.outside="mobileMenuVar = false"
            x-show="mobileMenuVar"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex w-full max-w-xs flex-1 flex-col bg-sky-950 pt-5 pb-4">

            <div x-show="mobileMenuVar"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute top-0 right-0 -mr-12 pt-2">
                <button type="button" @click="mobileMenuVar = !mobileMenuVar"
                    class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-white">
                    <span class="sr-only">Close sidebar</span>
                    <x-heroicons::outline.x-mark class="h-6 w-6 text-white"/>
                </button>
            </div>

            <div class="flex shrink-0 items-center px-4">
                <!--
                <x-application-logo class="h-8 w-auto fill-current text-white" alt="{{ config('app.name') }}" />
                -->
            </div>
            <div class="mt-5 h-0 flex-1 overflow-y-auto">
                @include('layouts.navigation-items')
            </div>
        </div>

        <div class="w-14 shrink-0" aria-hidden="true">
            <!-- Dummy element to force sidebar to shrink to fit close icon -->
        </div>
    </div>
</div>
