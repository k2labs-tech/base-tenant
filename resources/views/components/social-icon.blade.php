@props(['provider'])

{{--
    Las marcas van en su color: un botón de Google en gris no se reconoce como
    un botón de Google, que es justo lo que hace que se pulse sin pensar. Son
    los logotipos oficiales simplificados, en línea porque el paquete no
    compila ni sirve assets.
--}}
@switch($provider)
    @case('google')
        <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.06 12.25c0-.85-.08-1.67-.22-2.45H12v4.64h6.2a5.3 5.3 0 0 1-2.3 3.48v2.9h3.72c2.17-2 3.44-4.94 3.44-8.57z"/>
            <path fill="#34A853" d="M12 24c3.1 0 5.7-1.03 7.62-2.79l-3.72-2.89c-1.03.69-2.35 1.1-3.9 1.1-3 0-5.54-2.02-6.45-4.74H1.7v2.98A11.5 11.5 0 0 0 12 24z"/>
            <path fill="#FBBC05" d="M5.55 14.68a6.9 6.9 0 0 1 0-4.4V7.3H1.7a11.5 11.5 0 0 0 0 10.35l3.85-2.98z"/>
            <path fill="#EA4335" d="M12 4.75c1.69 0 3.2.58 4.4 1.72l3.3-3.3C17.7 1.28 15.1.25 12 .25A11.5 11.5 0 0 0 1.7 7.3l3.85 2.98C6.46 7.56 9 4.75 12 4.75z"/>
        </svg>
        @break

    @case('linkedin')
        <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#0A66C2" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.55V9h3.57v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"/>
        </svg>
        @break

    @case('microsoft')
        <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#F25022" d="M1 1h10.5v10.5H1z"/>
            <path fill="#7FBA00" d="M12.5 1H23v10.5H12.5z"/>
            <path fill="#00A4EF" d="M1 12.5h10.5V23H1z"/>
            <path fill="#FFB900" d="M12.5 12.5H23V23H12.5z"/>
        </svg>
        @break

    @default
        <flux:icon.globe-alt variant="micro" />
@endswitch
