<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('base-tenant::roles.title') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::roles.description') }}</flux:subheading>
        </div>

        @if($canCreate)
            <flux:button wire:click="toggleCreateForm" variant="primary" icon="plus">
                {{ __('base-tenant::roles.new_role') }}
            </flux:button>
        @endif
    </div>

    @if($showCreateForm)
        <form wire:submit="createRole" class="flex flex-col gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:input wire:model="newRoleName" :label="__('base-tenant::roles.name')" />
            </div>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="createRole">{{ __('base-tenant::common.create') }}</span>
                <span wire:loading wire:target="createRole">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </form>
    @endif

    {{-- Maestro-detalle, no dos columnas de sección: la izquierda es la lista de
         roles, no la explicación de un bloque. --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($roles as $role)
                        <li class="flex items-center justify-between gap-2 px-4 py-3 {{ $editingRole?->id === $role->id ? 'bg-accent-50 dark:bg-accent-950/40' : '' }}">
                            <button type="button" wire:click="edit('{{ $role->id }}')" class="min-w-0 flex-1 text-left">
                                <flux:heading size="sm">{{ $role->label }}</flux:heading>
                                <flux:text size="sm">
                                    {{ $role->permissions->count() }} {{ __('base-tenant::roles.permissions') }}
                                    @if($role->isGlobal())
                                        &middot; {{ __('base-tenant::roles.global') }}
                                    @endif
                                </flux:text>
                            </button>

                            @can('delete', $role)
                                <flux:button
                                    wire:click="confirmDelete('{{ $role->id }}')"
                                    variant="subtle"
                                    size="sm"
                                    icon="trash"
                                    :aria-label="__('base-tenant::common.delete')"
                                />
                            @endcan
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center">
                            <flux:text>{{ __('base-tenant::roles.none') }}</flux:text>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="lg:col-span-2">
            @if($editingRole)
                <form wire:submit="savePermissions" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="border-b border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <flux:heading size="sm">{{ $editingRole->label }}</flux:heading>

                        {{-- `edit()` sólo abre lo que la política deja actualizar, así
                             que esta rama no se alcanza hoy. Se conserva como red por
                             si la autorización cambia a mitad de sesión: el efecto
                             sería una matriz deshabilitada, no una en blanco. --}}
                        @cannot('update', $editingRole)
                            <flux:text size="sm" class="text-warning-600 dark:text-warning-400">
                                {{ __('base-tenant::roles.read_only') }}
                            </flux:text>
                        @endcannot
                    </div>

                    <div class="space-y-6 p-4">
                        @foreach($permissionGroups as $group => $permissions)
                            <flux:checkbox.group :label="__('base-tenant::permissions.groups.'.$group)">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach($permissions as $permission)
                                        <flux:checkbox
                                            wire:model="selectedPermissions"
                                            value="{{ $permission->name }}"
                                            :label="__('base-tenant::permissions.names.'.$permission->name)"
                                            :disabled="! auth()->user()->can('update', $editingRole)"
                                        />
                                    @endforeach
                                </div>
                            </flux:checkbox.group>
                        @endforeach
                    </div>

                    @can('update', $editingRole)
                        <div class="flex items-center gap-3 border-t border-zinc-200 dark:border-zinc-700 px-4 py-3">
                            <flux:button type="submit" variant="primary">
                                <span wire:loading.remove wire:target="savePermissions">{{ __('base-tenant::common.save') }}</span>
                                <span wire:loading wire:target="savePermissions">{{ __('base-tenant::common.saving') }}</span>
                            </flux:button>

                            <flux:button wire:click="cancelEdit" variant="ghost">
                                {{ __('base-tenant::common.cancel') }}
                            </flux:button>
                        </div>
                    @endcan
                </form>
            @else
                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                    <flux:text>{{ __('base-tenant::roles.select_role') }}</flux:text>
                </div>
            @endif
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal" name="confirm-role-deletion" class="min-w-[22rem]">
        @php($rolAEliminar = $this->deletingRole())

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ __('base-tenant::roles.confirm_delete_title', ['role' => $rolAEliminar?->label]) }}
                </flux:heading>
                <flux:subheading>{{ __('base-tenant::roles.confirm_delete') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="deleteRole('{{ $rolAEliminar?->getKey() }}')" variant="danger">
                    <span wire:loading.remove wire:target="deleteRole">{{ __('base-tenant::common.delete') }}</span>
                    <span wire:loading wire:target="deleteRole">{{ __('base-tenant::common.processing') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
