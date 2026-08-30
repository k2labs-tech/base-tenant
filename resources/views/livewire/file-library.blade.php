<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::files.title') }}
                    </h1>

                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>

                    {{-- El total ocupado junto al recuento: dos ficheros de un
                         giga y mil de un kilo son la misma lista y una factura
                         muy distinta. --}}
                    <flux:badge size="sm" color="zinc">
                        {{ \Illuminate\Support\Number::fileSize($summary['bytes'], precision: 1) }}
                    </flux:badge>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::files.description') }}
                </p>
            </div>

            <div class="w-full sm:w-72">
                <livewire:base-tenant.files.usage-badge />
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-zinc-50/60 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="min-w-56 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    type="search"
                    :placeholder="__('base-tenant::files.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @php $activos = ($filterKind !== '' ? 1 : 0) + ($filterCollection !== '' ? 1 : 0); @endphp

                    @if($activos > 0)
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $activos }}</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::files.kind') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterKind" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::files.kinds.all') }}</flux:select.option>
                            <flux:select.option value="image">{{ __('base-tenant::files.kinds.image') }}</flux:select.option>
                            <flux:select.option value="document">{{ __('base-tenant::files.kinds.document') }}</flux:select.option>
                        </flux:select>
                    </div>

                    @if(count($collections) > 1)
                        <flux:menu.separator />

                        <flux:menu.heading>{{ __('base-tenant::files.collection') }}</flux:menu.heading>

                        <div class="px-2 py-1.5">
                            <flux:select wire:model.live="filterCollection" size="sm">
                                <flux:select.option value="">{{ __('base-tenant::files.collection_all') }}</flux:select.option>
                                @foreach($collections as $option)
                                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:button.group>
                <flux:button
                    size="sm"
                    icon="bars-3"
                    :variant="$density === 'comfortable' ? 'filled' : 'ghost'"
                    wire:click="$set('density', 'comfortable')"
                    :aria-label="__('base-tenant::common.density_comfortable')"
                />
                <flux:button
                    size="sm"
                    icon="bars-4"
                    :variant="$density === 'compact' ? 'filled' : 'ghost'"
                    wire:click="$set('density', 'compact')"
                    :aria-label="__('base-tenant::common.density_compact')"
                />
            </flux:button.group>

            <flux:select wire:model.live="perPage" size="sm" class="w-20" :label:sr-only="__('base-tenant::common.per_page')">
                @foreach($perPageOptions as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterKind !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::files.kind') }}: {{ __('base-tenant::files.kinds.'.$filterKind) }}
                        <flux:badge.close wire:click="$set('filterKind', '')" />
                    </flux:badge>
                @endif

                @if($filterCollection !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::files.collection') }}: {{ $filterCollection }}
                        <flux:badge.close wire:click="$set('filterCollection', '')" />
                    </flux:badge>
                @endif

                <flux:link href="#" variant="subtle" class="text-xs" wire:click.prevent="resetFilters">
                    {{ __('base-tenant::common.clear_filters') }}
                </flux:link>
            </div>
        @endif

        @if($isEmptyTable)
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'folder-open'" variant="outline" class="size-5" />
                </div>

                @if($hasActiveFilters)
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ __('base-tenant::common.empty_search') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::common.empty_search_hint') }}
                    </p>

                    <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark" class="mt-4">
                        {{ __('base-tenant::common.clear_filters') }}
                    </flux:button>
                @else
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ __('base-tenant::files.empty') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::files.empty_hint') }}
                    </p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('name')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::files.column.name') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-40 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::files.column.collection') }}
                            </th>

                            <th scope="col" class="w-28 px-4 py-2.5 text-right">
                                <button type="button" wire:click="sort('size')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::files.column.size') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-44 px-4 py-2.5">
                                <button type="button" wire:click="sort('created_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::files.column.uploaded') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-16 px-4 py-2.5">
                                <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody wire:loading.delay wire:target="search,filterKind,filterCollection,sort,resetFilters,gotoPage,previousPage,nextPage,perPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:skeleton class="size-9 rounded-lg" />
                                        <div class="space-y-1.5">
                                            <flux:skeleton class="h-3 w-40" />
                                            <flux:skeleton class="h-2.5 w-24" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-4 w-20 rounded-full" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="ml-auto h-3 w-14" /></td>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-24" />
                                        <flux:skeleton class="h-2.5 w-16" />
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterKind,filterCollection,sort,resetFilters,gotoPage,previousPage,nextPage,perPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($files as $file)
                            <tr wire:key="file-{{ $file->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                {{-- Celda primaria compuesta: la miniatura, el
                                     nombre como dato y el tipo como metadato. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                            @if($file->isImage())
                                                <img src="{{ $file->variantUrl('thumb') ?? $file->url() }}" alt="" loading="lazy" class="size-full object-cover" />
                                            @else
                                                <flux:icon.document variant="outline" class="size-4 text-zinc-400 dark:text-zinc-500" />
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $file->name }}</div>
                                            <div class="truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $file->mime_type }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <flux:badge size="sm" color="zinc">{{ $file->collection }}</flux:badge>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <span class="whitespace-nowrap text-sm tabular-nums text-zinc-900 dark:text-white">{{ $file->humanSize() }}</span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="whitespace-nowrap text-sm tabular-nums text-zinc-900 dark:text-white">
                                        {{ $file->created_at?->isoFormat('D MMM YYYY') }}
                                    </div>
                                    <div class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $file->created_at?->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-down-tray"
                                            href="{{ route('base-tenant.files.show', $file) }}"
                                            class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                            :aria-label="__('base-tenant::files.download')"
                                        />

                                        @if($canDelete)
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('base-tenant::common.actions')" />

                                                <flux:menu>
                                                    <flux:menu.item
                                                        wire:click="confirmDelete('{{ $file->id }}')"
                                                        variant="danger"
                                                        icon="trash"
                                                    >
                                                        {{ __('base-tenant::common.delete') }}
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::common.showing', [
                        'from' => $files->firstItem(),
                        'to' => $files->lastItem(),
                        'total' => $files->total(),
                    ]) }}
                </p>

                <div>{{ $files->links() }}</div>
            </div>
        @endif
    </div>

    {{-- Nombrando el fichero: «¿Eliminar este fichero?» no dice cuál de los
         veinticinco de la tabla se va a ir. --}}
    <flux:modal wire:model="showDeleteModal" name="confirm-file-deletion" class="min-w-[22rem]">
        @php($aEliminar = $this->deletingFile())

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ __('base-tenant::files.confirm_delete_title', ['name' => $aEliminar?->name]) }}
                </flux:heading>
                <flux:subheading>{{ __('base-tenant::files.confirm_delete') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="remove" variant="danger">
                    {{ __('base-tenant::common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
