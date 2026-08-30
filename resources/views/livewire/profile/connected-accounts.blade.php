<div>
    @if($providers === [])
        {{-- Sin proveedores configurados no hay nada que conectar. Enseñar una
             lista vacía haría parecer que falta algo por hacer. --}}
    @else
        <div class="space-y-1">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::social.title') }}
            </h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::social.description') }}
            </p>
        </div>

        <ul class="mt-4 divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @foreach($providers as $provider)
                @php
                    $conectado = in_array($provider, $linked, true);

                    // Quitar el último proveedor de quien nunca puso contraseña
                    // dejaría una cuenta que nadie puede abrir: ni siquiera
                    // restableciendo, porque restablecer necesita una
                    // contraseña que restablecer.
                    $ultimaPuerta = $conectado && ! $hasPassword && count($linked) === 1;
                @endphp

                <li wire:key="social-{{ $provider }}" class="flex items-center justify-between gap-4 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <x-base-tenant::social-icon :provider="$provider" />

                        <div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                {{ \Base\Tenant\Social\SocialProviders::label($provider) }}
                            </div>

                            <div class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" data-social-state="{{ $conectado ? 'connected' : 'disconnected' }}">
                                <span @class(['size-1.5 rounded-full', 'bg-success-500' => $conectado, 'bg-zinc-300 dark:bg-zinc-600' => ! $conectado])></span>
                                {{ $conectado ? __('base-tenant::social.connected') : __('base-tenant::social.not_connected') }}
                            </div>
                        </div>
                    </div>

                    @if($conectado)
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="disconnect('{{ $provider }}')"
                            :disabled="$ultimaPuerta"
                        >
                            {{ __('base-tenant::social.disconnect') }}
                        </flux:button>
                    @else
                        <flux:button
                            size="sm"
                            variant="filled"
                            href="{{ route('base-tenant.social.redirect', ['provider' => $provider]) }}"
                        >
                            {{ __('base-tenant::social.connect') }}
                        </flux:button>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- El botón deshabilitado sin explicación es exactamente lo que hace
             que alguien escriba a soporte. --}}
        @if(! $hasPassword && count($linked) === 1)
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::social.errors.would_lock_out', [
                    'provider' => \Base\Tenant\Social\SocialProviders::label($linked[0]),
                ]) }}
            </p>
        @endif
    @endif
</div>
