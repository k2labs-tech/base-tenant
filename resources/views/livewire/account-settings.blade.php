<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('base-tenant::settings.title') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::settings.description') }}</flux:subheading>
    </div>

    @if(! $account)
        <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
            <flux:text>{{ __('base-tenant::settings.no_account') }}</flux:text>
        </div>
    @elseif($schemas->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
            <flux:text>{{ __('base-tenant::settings.no_schemas') }}</flux:text>
            <pre class="mt-3 inline-block rounded bg-zinc-100 dark:bg-zinc-800 p-3 text-left text-xs text-zinc-600 dark:text-zinc-300">Settings::register(BrandSettings::class);</pre>
        </div>
    @else
        {{-- Maestro-detalle: la columna izquierda son los grupos registrados, no
             la explicación de un bloque, así que no es el patrón de secciones. --}}
        <div class="grid gap-6 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <flux:navlist>
                    @foreach($schemas as $key => $schema)
                        <flux:navlist.item
                            wire:click="selectGroup('{{ $key }}')"
                            :current="$group === $key"
                            as="button"
                        >
                            {{ $schema::label() }}
                        </flux:navlist.item>
                    @endforeach
                </flux:navlist>
            </div>

            <div class="lg:col-span-3">
                <form wire:submit="save" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="space-y-5 p-4">
                        @foreach($fields as $field)
                            @php($label = __('base-tenant::settings.fields.'.$group.'.'.$field['name']))
                            @php($label = $label === 'base-tenant::settings.fields.'.$group.'.'.$field['name']
                                ? \Illuminate\Support\Str::headline($field['name'])
                                : $label)

                            <div wire:key="field-{{ $group }}-{{ $field['name'] }}">
                                @if($field['type'] === 'boolean')
                                    <flux:checkbox
                                        wire:model="values.{{ $field['name'] }}"
                                        :label="$label"
                                        :description="$group.'.'.$field['name']"
                                        :disabled="! $canEdit"
                                    />
                                    <flux:error name="values.{{ $field['name'] }}" />
                                @elseif($field['type'] === 'json')
                                    <flux:textarea
                                        wire:model="values.{{ $field['name'] }}"
                                        :label="$label"
                                        :description="__('base-tenant::settings.json_hint').' — '.$group.'.'.$field['name']"
                                        rows="5"
                                        class="font-mono text-xs"
                                        :disabled="! $canEdit"
                                    />
                                @elseif($field['type'] === 'integer')
                                    <flux:input
                                        wire:model="values.{{ $field['name'] }}"
                                        type="number"
                                        :label="$label"
                                        :description="$group.'.'.$field['name']"
                                        class="max-w-40"
                                        :disabled="! $canEdit"
                                    />
                                @else
                                    <flux:input
                                        wire:model="values.{{ $field['name'] }}"
                                        :label="$label"
                                        :description="$group.'.'.$field['name']"
                                        :disabled="! $canEdit"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($canEdit)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 px-4 py-3">
                            <flux:button type="submit" variant="primary">
                                <span wire:loading.remove wire:target="save">{{ __('base-tenant::common.save') }}</span>
                                <span wire:loading wire:target="save">{{ __('base-tenant::common.saving') }}</span>
                            </flux:button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif
</div>
