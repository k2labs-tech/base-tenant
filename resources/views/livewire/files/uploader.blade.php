@php
    // Lo que la zona necesita saber en el navegador. Todo esto es orientativo:
    // el servidor lo vuelve a comprobar contra los bytes que llegan.
    $config = [
        'collection' => $collection,
        'fileableType' => $fileableType,
        'fileableId' => $fileableId,
        'multiple' => $multiple,
        'maxSize' => $rules->maxSize,
        'signUrl' => route('base-tenant.files.sign'),
        'finalizeUrl' => route('base-tenant.files.finalize'),
    ];
@endphp

<div
    x-data="baseTenantUploader(@js($config))"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false; add($event.dataTransfer.files)"
    class="space-y-3"
>
    <label
        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
        :class="dragging
            ? 'border-accent-400 bg-accent-50 dark:border-accent-500 dark:bg-accent-950/30'
            : 'border-zinc-300 bg-zinc-50/60 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-zinc-600'"
    >
        <input
            type="file"
            class="sr-only"
            @if($multiple) multiple @endif
            @if($rules->acceptAttribute() !== '') accept="{{ $rules->acceptAttribute() }}" @endif
            x-on:change="add($event.target.files); $event.target.value = ''"
        />

        <div class="mb-3 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
            <flux:icon.arrow-up-tray variant="outline" class="size-5" />
        </div>

        <p class="text-sm font-semibold text-zinc-900 dark:text-white">
            {{ __('base-tenant::files.drop_here') }}
        </p>

        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            @if($rules->maxSize)
                {{ __('base-tenant::files.max_size', ['size' => \Illuminate\Support\Number::fileSize($rules->maxSize)]) }}
            @else
                {{ __('base-tenant::files.any_size') }}
            @endif
        </p>
    </label>

    {{-- Una fila por fichero en vuelo, con su barra y su fallo propio: un
         error global no dice cuál de los seis falló ni deja reintentarlo. --}}
    <template x-if="queue.length">
        <ul class="divide-y divide-zinc-100 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            <template x-for="item in queue" :key="item.id">
                <li class="flex items-center gap-3 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="truncate text-sm text-zinc-900 dark:text-white" x-text="item.name"></span>
                            <span class="shrink-0 text-xs tabular-nums text-zinc-500 dark:text-zinc-400" x-text="item.status === 'error' ? '' : item.progress + '%'"></span>
                        </div>

                        <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" x-show="item.status !== 'error'">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="item.status === 'done' ? 'bg-success-500' : 'bg-accent-500'"
                                :style="`width: ${item.progress}%`"
                            ></div>
                        </div>

                        <p class="mt-1 text-xs text-danger-600 dark:text-danger-400" x-show="item.status === 'error'" x-text="item.error"></p>
                    </div>

                    <flux:button
                        size="xs"
                        variant="ghost"
                        icon="arrow-path"
                        x-show="item.status === 'error'"
                        x-on:click="retry(item)"
                        :aria-label="__('base-tenant::files.retry')"
                    />
                </li>
            </template>
        </ul>
    </template>
</div>

@once
    @push('scripts')
        <script>
            function baseTenantUploader(config) {
                return {
                    dragging: false,
                    queue: [],
                    active: 0,

                    // Tres a la vez. Una sola conexión desaprovecha el ancho de
                    // banda en ficheros grandes; abrir una por fichero satura la
                    // subida y las hace competir entre ellas.
                    concurrency: 3,

                    add(fileList) {
                        for (const file of fileList) {
                            if (config.maxSize && file.size > config.maxSize) {
                                this.queue.push({
                                    id: crypto.randomUUID(),
                                    file,
                                    name: file.name,
                                    progress: 0,
                                    status: 'error',
                                    attempts: 0,
                                    error: @js(__('base-tenant::files.too_large')),
                                });

                                continue;
                            }

                            this.queue.push({
                                id: crypto.randomUUID(),
                                file,
                                name: file.name,
                                progress: 0,
                                status: 'waiting',
                                attempts: 0,
                                error: null,
                            });
                        }

                        this.pump();
                    },

                    pump() {
                        while (this.active < this.concurrency) {
                            const next = this.queue.find((item) => item.status === 'waiting');

                            if (! next) return;

                            this.active++;
                            this.send(next).finally(() => {
                                this.active--;
                                this.pump();
                            });
                        }
                    },

                    retry(item) {
                        item.status = 'waiting';
                        item.error = null;
                        item.progress = 0;
                        this.pump();
                    },

                    async send(item) {
                        item.status = 'uploading';

                        try {
                            const signature = await this.post(config.signUrl, {
                                name: item.file.name,
                                content_type: item.file.type || 'application/octet-stream',
                                size: item.file.size,
                                collection: config.collection,
                                fileable_type: config.fileableType,
                                fileable_id: config.fileableId,
                            });

                            await this.put(signature.url, item, signature.headers || {});

                            const stored = await this.post(config.finalizeUrl, {
                                key: signature.key,
                                name: item.file.name,
                                collection: config.collection,
                                fileable_type: config.fileableType,
                                fileable_id: config.fileableId,
                            });

                            item.progress = 100;
                            item.status = 'done';

                            this.$wire.uploaded(stored.id);

                            // Se va sola al terminar: una lista que solo crece
                            // acaba tapando la zona de soltar.
                            setTimeout(() => {
                                this.queue = this.queue.filter((queued) => queued.id !== item.id);
                            }, 1500);
                        } catch (error) {
                            // Tres intentos con espera creciente: un corte de red
                            // en mitad de una subida larga es lo normal, no una
                            // excepción, y volver a empezar a mano es peor que
                            // esperar dos segundos.
                            if (item.attempts < 2 && error.retryable !== false) {
                                item.attempts++;
                                item.progress = 0;

                                await new Promise((resolve) => setTimeout(resolve, 1000 * item.attempts));

                                return this.send(item);
                            }

                            item.status = 'error';
                            item.error = error.message || @js(__('base-tenant::files.upload_failed'));
                        }
                    },

                    put(url, item, headers) {
                        return new Promise((resolve, reject) => {
                            const request = new XMLHttpRequest();

                            request.open('PUT', url);

                            for (const [header, value] of Object.entries(headers)) {
                                request.setRequestHeader(header, value);
                            }

                            // El progreso viene de la subida, no de la respuesta:
                            // sin esto la barra salta de 0 a 100 y no informa de
                            // nada durante el minuto que dura.
                            request.upload.onprogress = (event) => {
                                if (event.lengthComputable) {
                                    item.progress = Math.round((event.loaded / event.total) * 95);
                                }
                            };

                            request.onload = () => request.status < 400
                                ? resolve()
                                : reject(new Error(`HTTP ${request.status}`));

                            request.onerror = () => reject(new Error(@js(__('base-tenant::files.network_error'))));

                            request.send(item.file);
                        });
                    },

                    async post(url, body) {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify(body),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (! response.ok) {
                            const error = new Error(payload.message || `HTTP ${response.status}`);

                            // Un 4xx no mejora repitiéndolo: el fichero es
                            // demasiado grande, o del tipo que no se acepta, o
                            // la cuota está llena.
                            error.retryable = response.status >= 500;

                            throw error;
                        }

                        return payload;
                    },
                };
            }
        </script>
    @endpush
@endonce
