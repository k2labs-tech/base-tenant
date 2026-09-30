<!DOCTYPE html>
<html>
<head>
    @livewireStyles
</head>
<body>
    <main data-host-guest-layout>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
