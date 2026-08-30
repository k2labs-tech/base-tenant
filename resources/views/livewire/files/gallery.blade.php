<div>
    @if($files->isEmpty())
        <div class="flex flex-col items-center rounded-xl border border-zinc-200 bg-white px-6 py-12 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-3 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                <flux:icon.photo variant="outline" class="size-5" />
            </div>

            <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                {{ __('base-tenant::files.empty') }}
            </p>

            <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::files.empty_hint') }}
            </p>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($files as $file)
                <li wire:key="file-{{ $file->id }}" class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white transition-colors hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                    <div class="flex aspect-square items-center justify-center bg-zinc-50 dark:bg-zinc-950">
                        @if($file->isImage())
                            {{-- La miniatura si ya está; si el job aún no ha
                                 pasado, el original: enseñar un hueco mientras
                                 tanto haría parecer que la subida falló. --}}
                            <img
                                src="{{ $file->variantUrl('thumb') ?? $file->url() }}"
                                alt="{{ $file->name }}"
                                loading="lazy"
                                class="size-full object-cover"
                            />
                        @else
                            <flux:icon.document variant="outline" class="size-8 text-zinc-400 dark:text-zinc-600" />
                        @endif
                    </div>

                    <div class="border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" title="{{ $file->name }}">
                            {{ $file->name }}
                        </p>
                        <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ $file->humanSize() }}
                        </p>
                    </div>

                    {{-- Las acciones se revelan al pasar el ratón, y siguen
                         alcanzables con el teclado por el foco. --}}
                    <div class="absolute right-2 top-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                        <flux:button
                            size="xs"
                            variant="filled"
                            icon="arrow-down-tray"
                            href="{{ route('base-tenant.files.show', $file) }}"
                            :aria-label="__('base-tenant::files.download')"
                        />

                        @if($canDelete)
                            <flux:button
                                size="xs"
                                variant="danger"
                                icon="trash"
                                wire:click="confirmDelete('{{ $file->id }}')"
                                :aria-label="__('base-tenant::common.delete')"
                            />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal wire:model="showDeleteModal" name="confirm-gallery-file-deletion" class="min-w-[22rem]">
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
