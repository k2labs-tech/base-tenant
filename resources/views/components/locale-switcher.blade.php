<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-primary-700 hover:bg-surface-100 rounded-lg transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
        </svg>
        <span>{{ strtoupper(app()->getLocale()) }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open"
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black/5"
         style="display: none;">
        <div class="py-1">
            <a href="{{ route('locale.switch', 'en') }}" class="flex items-center px-4 py-2 text-sm text-primary-700 hover:bg-surface-100 {{ app()->getLocale() == 'en' ? 'bg-surface-50' : '' }}">
                <span class="mr-3">🇬🇧</span>
                English
            </a>
            <a href="{{ route('locale.switch', 'es') }}" class="flex items-center px-4 py-2 text-sm text-primary-700 hover:bg-surface-100 {{ app()->getLocale() == 'es' ? 'bg-surface-50' : '' }}">
                <span class="mr-3">🇪🇸</span>
                Español
            </a>
        </div>
    </div>
</div>
