<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Aplica el tema guardado antes del primer pintado: sin esto la página
         parpadea en claro antes de virar a oscuro. --}}
    @fluxAppearance
</head>
<body class="min-h-screen font-sans antialiased bg-white text-zinc-900 dark:bg-zinc-900 dark:text-white">
    <flux:toast />

    {{-- `collapsible` sin valor equivale a `collapsible="true"`: cajón en móvil
         y plegado a iconos en escritorio. Flux se encarga de recordar el estado
         de escritorio entre visitas. --}}
    <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-950">
        <flux:sidebar.header>
            <flux:sidebar.brand :href="route('base-tenant.dashboard')" :name="config('app.name', 'Laravel')" wire:navigate>
                <x-slot name="logo" class="bg-accent-500 text-white">
                    <flux:icon.bolt variant="micro" />
                </x-slot>
            </flux:sidebar.brand>

            <flux:sidebar.collapse class="max-lg:hidden" />
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
        </flux:sidebar.header>

        @include('base-tenant::layouts.navigation-items')

        <flux:sidebar.spacer />

        @include('base-tenant::layouts.user-menu')
    </flux:sidebar>

    <flux:header sticky class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" />

        {{-- Las migas salen del menú: una entrada que se oculte por permiso o
             por feature desaparece también de aquí, sin mantener una segunda
             lista. En móvil se ocultan, donde no caben y el título de la
             pantalla ya dice dónde estás. --}}
        @php($rastro = Base\Tenant\Facades\Menu::trail(Auth::user()))

        @if($rastro !== [])
            <flux:breadcrumbs class="max-sm:hidden">
                @foreach($rastro as $paso)
                    <flux:breadcrumbs.item :href="$paso['href']" wire:navigate>
                        {{ $paso['title'] }}
                    </flux:breadcrumbs.item>
                @endforeach
            </flux:breadcrumbs>
        @endif

        <flux:spacer />

        <div class="hidden sm:block">
            @livewire('base-tenant.account-switcher')
        </div>

        @livewire('base-tenant.notification-bell')
    </flux:header>

    <flux:main container>
        {{-- El banner vive dentro de `flux:main` a propósito: `flux.css` monta
             la rejilla del armazón sobre los hijos directos del `body` y sólo
             conoce las áreas `header`, `sidebar`, `main`, `footer` y `aside`.
             Un hijo suelto entre la cabecera y el contenido se colocaría solo y
             rompería la rejilla. --}}
        @impersonating
            <flux:callout variant="warning" icon="exclamation-triangle" inline class="mb-6">
                <flux:callout.text>
                    {{ __('base-tenant::users.impersonation_banner', ['name' => Auth::user()->name]) }}
                </flux:callout.text>

                <x-slot name="actions">
                    <flux:button size="sm" :href="route('impersonate.leave')">
                        {{ __('base-tenant::users.leave_impersonation') }}
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endImpersonating

        {{-- Los redirects del paquete dejan su aviso en `status` o `error`.
             Sin este bloque la persona aterriza sin saber si lo que hizo
             funcionó. --}}
        @if(session('status'))
            <flux:callout variant="success" icon="check-circle" inline class="mb-6">
                <flux:callout.text>{{ session('status') }}</flux:callout.text>
            </flux:callout>
        @endif

        @if(session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle" inline class="mb-6">
                <flux:callout.text>{{ session('error') }}</flux:callout.text>
            </flux:callout>
        @endif

        {{ $slot }}
    </flux:main>

    @livewireScripts
    @fluxScripts

    {{-- Los componentes que traen su propio JavaScript lo empujan
         aquí. Sin esta pila, un `@push('scripts')` se pierde sin
         avisar y el componente queda inerte en la página. --}}
    @stack('scripts')
</body>
</html>
