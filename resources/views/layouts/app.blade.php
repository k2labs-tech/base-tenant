<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />


    <!-- Scripts -->
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans antialiased h-full bg-surface-50 text-primary-800 overflow-hidden">
    <flux:toast />
    <div class="h-screen flex" x-data="{
        sidebarOpen: false,
        sidebarCollapsed: false,
        init() {
            this.sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
        }
    }">
        <!-- Mobile sidebar backdrop -->
        <div
            x-show="sidebarOpen"
            x-cloak
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-primary-900/50 backdrop-blur-xs z-40 lg:hidden"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="{
                'w-64': !sidebarCollapsed,
                'w-20': sidebarCollapsed,
                'translate-x-0': sidebarOpen,
                '-translate-x-full': !sidebarOpen
            }"
            class="fixed inset-y-0 left-0 z-50 bg-white border-r border-surface-200 transition-all duration-300 ease-in-out transform lg:translate-x-0 lg:static lg:inset-0 flex flex-col h-full"
        >
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="flex items-center justify-between h-14 px-4 border-b border-surface-200 shrink-0">
                    <a href="{{ route('base-tenant.dashboard') }}" class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-accent-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span x-show="!sidebarCollapsed" class="text-lg font-semibold text-primary-900">
                            {{ config('app.name', 'Laravel') }}
                        </span>
                    </a>
                    <button
                        @click="toggleSidebar()"
                        class="hidden lg:block p-1.5 rounded-lg hover:bg-surface-100 transition-colors"
                    >
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto overflow-x-hidden">
                    @include('base-tenant::layouts.navigation-items')
                </nav>

                <!-- User menu -->
                <div class="border-t border-surface-200 p-3 shrink-0">
                    <div class="flex items-center space-x-3">
                        <div class="shrink-0">
                            <div class="w-10 h-10 bg-accent-100 rounded-full flex items-center justify-center">
                                <span class="text-accent-600 font-medium text-sm">
                                    {{ substr(Auth::user()->name, 0, 2) }}
                                </span>
                            </div>
                        </div>
                        <div x-show="!sidebarCollapsed" class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-primary-900 truncate">
                                {{ Auth::user()->name }}
                            </p>
                            <p class="text-xs text-primary-500 truncate">
                                {{ Auth::user()->email }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Top header -->
            <header class="bg-white border-b border-surface-200 shrink-0">
                <div class="flex items-center justify-between h-14 px-4 sm:px-6 lg:px-8">
                    <!-- Mobile menu button -->
                    <button
                        @click="sidebarOpen = true"
                        class="p-2 rounded-lg hover:bg-surface-100 transition-colors lg:hidden"
                    >
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Breadcrumb or page title -->
                    <div class="flex-1">
                        @if (isset($header))
                            <h1 class="text-2xl font-semibold text-primary-900">
                                {{ $header }}
                            </h1>
                        @endif
                    </div>

                    <!-- Header actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Account Switcher -->
                        @livewire('base-tenant.account-switcher')

                        <!-- Notifications -->
                        @livewire('base-tenant.notification-bell')
                        <div
                            x-data="{ userDropdownMenu: false }"
                            class="ml-4 items-center md:ml-6 inline-flex">
                            <div class="relative ml-3">
                                <div class="inline-flex capitalize text-sm">
                                    {{ auth()->user()->name }}
                                </div>
                                <div class="inline-flex ml-2">

                                    <button type="button"
                                            @click="userDropdownMenu = !userDropdownMenu"
                                            class="flex max-w-xs items-center rounded-full bg-accent-600 hover:bg-accent-700 text-white text-sm focus:outline-hidden transition-colors"
                                            id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                        <div class="w-8 h-8 rounded-full bg-accent-600 hover:bg-accent-700 text-white flex items-center justify-center text-sm font-medium">
                                            {{ auth()->user()->initials }}
                                        </div>
                                    </button>
                                </div>

                                @include('base-tenant::layouts.navigation-dropdown')
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Impersonation Banner -->
            @impersonating
                <div class="bg-warning-100 border-b border-warning-200 shrink-0">
                    <div class="flex items-center justify-between h-12 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-sm font-medium text-warning-800">
                                {{ __('base-tenant::users.impersonation_banner', ['name' => Auth::user()->name]) }}
                            </span>
                        </div>
                        <a href="{{ route('impersonate.leave') }}" class="inline-flex items-center px-4 py-2 bg-zinc-900 text-white text-sm font-bold rounded-lg hover:bg-zinc-800 focus:outline-hidden focus:ring-2 focus:ring-zinc-700 focus:ring-offset-2 shadow-lg transition-all">
                            {{ __('base-tenant::users.leave_impersonation') }}
                        </a>
                    </div>
                </div>
            @endImpersonating

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto bg-surface-100">
                <div class="py-4">
                    <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>
    @fluxScripts
</body>
</html>
