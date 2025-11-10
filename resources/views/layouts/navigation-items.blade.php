<div class="space-y-1" x-data="{
    mainMenuOpen: localStorage.getItem('mainMenuOpen') !== 'false',
    settingsMenuOpen: localStorage.getItem('settingsMenuOpen') !== 'false',
    toggleMenu(menu) {
        if (menu === 'main') {
            this.mainMenuOpen = !this.mainMenuOpen;
            localStorage.setItem('mainMenuOpen', this.mainMenuOpen);
        } else if (menu === 'settings') {
            this.settingsMenuOpen = !this.settingsMenuOpen;
            localStorage.setItem('settingsMenuOpen', this.settingsMenuOpen);
        }
    }
}">
    <a href="{{ route('base-tenant.dashboard') }}"
       class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->routeIs('base-tenant.dashboard')
                 ? 'bg-accent-50 text-accent-700 shadow-xs'
                 : 'text-primary-600 hover:bg-surface-100 hover:text-primary-900' }}">
        <div class="flex items-center">
            <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                        {{ request()->routeIs('base-tenant.dashboard') ? 'text-accent-600' : 'text-primary-400 group-hover:text-primary-600' }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.dashboard') }}</span>
        </div>
        <div x-show="!sidebarCollapsed" class="flex items-center space-x-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <svg class="-ml-0.5 mr-1 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                {{ __('base-tenant::app.status.active') }}
            </span>
        </div>
    </a>

    <!-- Quick Stats -->
    <div x-show="!sidebarCollapsed" class="mx-3 mt-4 p-3 bg-gradient-to-r from-accent-50 to-primary-50 rounded-lg border border-accent-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-primary-700">{{ __('base-tenant::app.stats.weekly_progress') }}</span>
            <span class="text-xs text-primary-500">{{ __('base-tenant::app.stats.this_week') }}</span>
        </div>
        <div class="space-y-2">
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-primary-600">{{ __('base-tenant::app.stats.completed') }}</span>
                    <span class="font-medium text-primary-700">78%</span>
                </div>
                <div class="w-full bg-primary-100 rounded-full h-1.5">
                    <div class="bg-gradient-to-r from-accent-400 to-accent-600 h-1.5 rounded-full" style="width: 78%"></div>
                </div>
            </div>
            <div class="flex justify-between items-center pt-1">
                <x-base-tenant::mini-chart :data="[40, 65, 55, 80, 70, 90, 85]" color="accent" />
                <span class="text-xs font-medium text-green-600">+23%</span>
            </div>
        </div>
    </div>

    <!-- Main Menu Section -->
    <div class="pt-4">
        <button @click="toggleMenu('main')" x-show="!sidebarCollapsed"
                class="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold text-primary-400 uppercase tracking-wider hover:text-primary-600 transition-colors">
            <span>{{ __('base-tenant::app.navigation.main_menu') }}</span>
            <svg class="h-4 w-4 transition-transform duration-200" :class="{'rotate-180': !mainMenuOpen}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="mainMenuOpen || sidebarCollapsed" x-collapse class="mt-2 space-y-1">

            <a href="#"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      text-primary-600 hover:bg-surface-100 hover:text-primary-900">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                text-primary-400 group-hover:text-primary-600"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.tasks') }}</span>
                </div>
                <div x-show="!sidebarCollapsed" class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-smtext-xs font-medium bg-accent-100 text-accent-700">
                        24
                    </span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 animate-pulse">
                        3
                    </span>
                </div>
            </a>

            <a href="#"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      text-primary-600 hover:bg-surface-100 hover:text-primary-900">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                text-primary-400 group-hover:text-primary-600"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.projects') }}</span>
                </div>
                <div x-show="!sidebarCollapsed" class="flex items-center">
                    <div class="flex -space-x-1">
                        <span class="inline-block h-6 w-6 rounded-full bg-primary-200 text-xs flex items-center justify-center text-primary-700 ring-2 ring-white">8</span>
                        <span class="inline-block h-6 w-6 rounded-full bg-accent-200 text-xs flex items-center justify-center text-accent-700 ring-2 ring-white">5</span>
                    </div>
                </div>
            </a>

            <a href="#"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      text-primary-600 hover:bg-surface-100 hover:text-primary-900">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                text-primary-400 group-hover:text-primary-600"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.team') }}</span>
                </div>
                <div x-show="!sidebarCollapsed" class="flex items-center space-x-1">
                    <span class="text-xs text-primary-500">32 {{ __('base-tenant::app.members') }}</span>
                    <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                </div>
            </a>

            <a href="#"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      text-primary-600 hover:bg-surface-100 hover:text-primary-900">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                text-primary-400 group-hover:text-primary-600"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.analytics') }}</span>
                </div>
                <div x-show="!sidebarCollapsed" class="flex items-center">
                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <span class="ml-1 text-xs font-medium text-green-600">+12%</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Settings Section -->
    <div class="pt-4">
        <button @click="toggleMenu('settings')" x-show="!sidebarCollapsed"
                class="w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold text-primary-400 uppercase tracking-wider hover:text-primary-600 transition-colors">
            <span>{{ __('base-tenant::app.navigation.settings') }}</span>
            <svg class="h-4 w-4 transition-transform duration-200" :class="{'rotate-180': !settingsMenuOpen}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="settingsMenuOpen || sidebarCollapsed" x-collapse class="mt-2 space-y-1">
            <a href="{{ route('base-tenant.profile') }}"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      {{ request()->routeIs('base-tenant.profile')
                         ? 'bg-accent-50 text-accent-700 shadow-xs'
                         : 'text-primary-600 hover:bg-surface-100 hover:text-primary-900' }}">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                {{ request()->routeIs('base-tenant.profile') ? 'text-accent-600' : 'text-primary-400 group-hover:text-primary-600' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.profile') }}</span>
                </div>
                <div x-show="!sidebarCollapsed">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        90%
                    </span>
                </div>
            </a>

            <a href="#"
               class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
                      text-primary-600 hover:bg-surface-100 hover:text-primary-900">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 shrink-0 transition-colors duration-200
                                text-primary-400 group-hover:text-primary-600"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="!sidebarCollapsed">{{ __('base-tenant::app.navigation.settings') }}</span>
                </div>
                <div x-show="!sidebarCollapsed">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                    </span>
                </div>
            </a>
        </div>
    </div>

    <!-- Bottom Stats Card -->
    <div x-show="!sidebarCollapsed" class="mx-3 mt-6 p-3 bg-surface-50 rounded-lg border border-surface-200">
        <h4 class="text-xs font-semibold text-primary-700 mb-3">{{ __('base-tenant::app.stats.quick_stats') }}</h4>
        <div class="grid grid-cols-2 gap-3">
            <div class="text-center">
                <div class="text-2xl font-bold text-accent-600">89</div>
                <div class="text-xs text-primary-500">{{ __('base-tenant::app.stats.active_items') }}</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">156</div>
                <div class="text-xs text-primary-500">{{ __('base-tenant::app.stats.total_items') }}</div>
            </div>
        </div>
        <div class="mt-3 pt-3 border-t border-surface-200">
            <div class="flex items-center justify-between text-xs">
                <span class="text-primary-500">{{ __('base-tenant::app.stats.efficiency') }}</span>
                <div class="flex items-center">
                    <div class="w-16 bg-surface-200 rounded-full h-1.5 mr-2">
                        <div class="bg-gradient-to-r from-green-400 to-green-600 h-1.5 rounded-full" style="width: 92%"></div>
                    </div>
                    <span class="font-medium text-green-600">92%</span>
                </div>
            </div>
        </div>
    </div>
</div>
