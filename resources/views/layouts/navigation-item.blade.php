@if(empty($item['children']))
    <flux:sidebar.item
        :href="$item['href'] ?? '#'"
        :icon="$item['icon']"
        :current="$item['active']"
        :badge="$item['badge']"
        :target="$item['target']"
        wire:navigate
    >{{ $item['title'] }}</flux:sidebar.item>
@else
    {{-- Una entrada con hijos sin icono propio se quedaría invisible al plegar
         la barra: `flux:sidebar.group` esconde el desplegable y no pone nada en
         su lugar. `folder` es el sustituto neutro para ese caso. --}}
    <flux:sidebar.group
        expandable
        :icon="$item['icon'] ?? 'folder'"
        :heading="$item['title']"
        :expanded="$item['active']"
    >
        @foreach($item['children'] as $hijo)
            @include('base-tenant::layouts.navigation-item', ['item' => $hijo])
        @endforeach
    </flux:sidebar.group>
@endif
