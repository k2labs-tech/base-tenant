@php
    use Base\Tenant\Facades\Menu;

    // Icono por sección de `base-tenant.menu.default_menus`. No es decoración:
    // `flux:sidebar.group` sólo emite el desplegable al vuelo —lo único visible
    // con la barra plegada— cuando el grupo trae icono. Una sección sin icono
    // desaparece por completo al plegar, así que siempre hay uno de reserva.
    $iconosDeSeccion = [
        // Rejilla de paneles: el área de trabajo, donde vive el día a día.
        'main' => 'squares-2x2',
        // El mismo engranaje que el menú de usuario usa para los ajustes.
        'settings' => 'cog-6-tooth',
    ];

    $secciones = collect(config('base-tenant.menu.default_menus', ['main', 'settings']))
        ->map(fn (string $clave): array => [
            'clave' => $clave,
            'icono' => $iconosDeSeccion[$clave] ?? 'folder',
            'items' => Menu::tree($clave),
        ])
        ->filter(fn (array $seccion): bool => $seccion['items']->isNotEmpty());
@endphp

<flux:sidebar.nav>
    @foreach($secciones as $seccion)
        <flux:sidebar.group
            expandable
            :icon="$seccion['icono']"
            :heading="__('base-tenant::menus.names.'.$seccion['clave'])"
        >
            @foreach($seccion['items'] as $item)
                @include('base-tenant::layouts.navigation-item', ['item' => $item])
            @endforeach
        </flux:sidebar.group>
    @endforeach
</flux:sidebar.nav>

{{-- Un contador que baja sin recargar: cualquier componente puede lanzar
     `menu-badges-refresh` (p. ej. `$this->dispatch('menu-badges-refresh')`
     tras completar una tarea) y la barra pide los números de nuevo. Sólo
     actualiza los contadores que ya se ven; uno que aparece de cero llega con
     la siguiente navegación. --}}
<script data-navigate-once>
    window.addEventListener('menu-badges-refresh', () => {
        clearTimeout(window.__baseTenantMenuBadges);
        window.__baseTenantMenuBadges = setTimeout(async () => {
            const response = await fetch(@js(route('base-tenant.menu.badges')), { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                return;
            }

            const badges = await response.json();

            document.querySelectorAll('[data-menu-badge]').forEach((badge) => {
                const value = badges[badge.dataset.menuBadge];

                if (value === undefined) {
                    return;
                }

                badge.textContent = value ?? '';
                badge.hidden = value === null;
            });
        }, 300);
    });
</script>
