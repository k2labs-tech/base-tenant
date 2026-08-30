@php
    use Base\Tenant\Social\SocialProviders;

    $providers = SocialProviders::enabled();
@endphp

@if($providers !== [])
    <div class="space-y-3">
        {{-- El separador va encima de los botones y no debajo: quien llega a
             esta pantalla con cuenta de Google no debería tener que leer el
             formulario entero para descubrir que no le hace falta. --}}
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></div>
            <span class="text-xs uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ $label ?? __('base-tenant::social.continue_with') }}
            </span>
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></div>
        </div>

        @php
            // Clases literales: Tailwind lee el código fuente, y una clase
            // construida en tiempo de ejecución no aparece en ningún fichero,
            // así que nunca se compila y la rejilla no se aplica.
            $grid = match (count($providers)) {
                1 => 'grid gap-2',
                2 => 'grid gap-2 sm:grid-cols-2',
                default => 'grid gap-2 sm:grid-cols-3',
            };
        @endphp

        <div class="{{ $grid }}">
            @foreach($providers as $provider)
                <a
                    href="{{ route('base-tenant.social.redirect', ['provider' => $provider]) }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    data-social-provider="{{ $provider }}"
                >
                    <x-base-tenant::social-icon :provider="$provider" />
                    {{ SocialProviders::label($provider) }}
                </a>
            @endforeach
        </div>
    </div>
@endif
