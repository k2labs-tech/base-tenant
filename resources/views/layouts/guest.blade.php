<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    <flux:toast />

    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
        <a href="/" wire:navigate class="mb-6">
            <x-base-tenant::application-logo class="h-16 w-16 fill-current text-zinc-500 dark:text-zinc-400" />
        </a>

        <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
    @fluxScripts

    {{-- Los componentes que traen su propio JavaScript lo empujan
         aquí. Sin esta pila, un `@push('scripts')` se pierde sin
         avisar y el componente queda inerte en la página. --}}
    @stack('scripts')
</body>
</html>
