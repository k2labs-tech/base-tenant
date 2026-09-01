<div
    class="space-y-4"
    x-data="{
        busy: false,
        error: '',
        name: '',

        /* base64url in and out: the browser speaks ArrayBuffer, the server
           speaks the spec's base64url, and mixing the two up is the usual
           reason a first passkey implementation silently fails. */
        decode(value) {
            const padded = value.replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(padded + '='.repeat((4 - padded.length % 4) % 4));
            return Uint8Array.from(raw, c => c.charCodeAt(0));
        },
        encode(buffer) {
            return btoa(String.fromCharCode(...new Uint8Array(buffer)))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        },

        async register() {
            this.error = '';

            if (!window.PublicKeyCredential) {
                this.error = @js(__('base-tenant::passkeys.unsupported'));
                return;
            }

            if (!this.name.trim()) return;

            this.busy = true;

            try {
                const optionsResponse = await fetch(@js(route('base-tenant.passkeys.register-options')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
                });

                const options = await optionsResponse.json();

                options.challenge = this.decode(options.challenge);
                options.user.id = this.decode(options.user.id);
                (options.excludeCredentials || []).forEach(c => c.id = this.decode(c.id));

                const credential = await navigator.credentials.create({ publicKey: options });

                const payload = {
                    id: credential.id,
                    rawId: this.encode(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: this.encode(credential.response.clientDataJSON),
                        attestationObject: this.encode(credential.response.attestationObject),
                    },
                };

                const saved = await fetch(@js(route('base-tenant.passkeys.register')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ credential: JSON.stringify(payload), name: this.name }),
                });

                if (!saved.ok) {
                    this.error = (await saved.json()).message ?? @js(__('base-tenant::passkeys.register_failed'));
                    return;
                }

                this.name = '';
                $wire.$refresh();
            } catch (e) {
                /* A user who closes the system dialog lands here, and that is
                   not an error worth shouting about. */
                this.error = e.name === 'NotAllowedError'
                    ? @js(__('base-tenant::passkeys.cancelled'))
                    : @js(__('base-tenant::passkeys.register_failed'));
            } finally {
                this.busy = false;
            }
        },
    }"
>
    <div class="space-y-1">
        <flux:heading size="lg">{{ __('base-tenant::passkeys.title') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::passkeys.description') }}</flux:subheading>
    </div>

    @if(! $enabled)
        <flux:callout variant="secondary" icon="information-circle">
            {{ __('base-tenant::passkeys.unsupported') }}
        </flux:callout>
    @else
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::passkeys.why') }}</p>

        @if($hasTotp)
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::passkeys.still_needs_totp') }}</p>
        @endif

        @if($passkeys->isEmpty())
            <div class="rounded-xl border border-zinc-200 px-4 py-8 text-center dark:border-zinc-800">
                <flux:icon.finger-print class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-white">
                    {{ __('base-tenant::passkeys.empty_title') }}
                </p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::passkeys.empty_description') }}
                </p>
            </div>
        @else
            <ul class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach($passkeys as $passkey)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <flux:icon.finger-print class="size-5 text-zinc-400 dark:text-zinc-500" />
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $passkey->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('base-tenant::passkeys.added', ['when' => $passkey->created_at->diffForHumans()]) }}
                                    ·
                                    {{ $passkey->last_used_at
                                        ? __('base-tenant::passkeys.last_used', ['when' => $passkey->last_used_at->diffForHumans()])
                                        : __('base-tenant::passkeys.never_used') }}
                                </p>
                            </div>
                        </div>

                        <flux:modal.trigger name="remove-passkey-{{ $passkey->id }}">
                            <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('base-tenant::passkeys.remove')" />
                        </flux:modal.trigger>

                        <flux:modal name="remove-passkey-{{ $passkey->id }}" class="md:w-96">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('base-tenant::passkeys.remove_title') }}</flux:heading>

                                <flux:text>
                                    {{ __('base-tenant::passkeys.remove_confirm', ['name' => $passkey->name]) }}
                                </flux:text>

                                <div class="flex justify-end gap-2">
                                    <flux:modal.close>
                                        <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                                    </flux:modal.close>

                                    <flux:button variant="danger" wire:click="remove({{ $passkey->id }})">
                                        {{ __('base-tenant::passkeys.remove') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-56 flex-1">
                <flux:input
                    x-model="name"
                    :label="__('base-tenant::passkeys.name_label')"
                    :placeholder="__('base-tenant::passkeys.name_placeholder')"
                />
            </div>

            <flux:button variant="primary" x-on:click="register()" x-bind:disabled="busy || !name.trim()">
                {{ __('base-tenant::passkeys.add') }}
            </flux:button>
        </div>

        <p x-show="error" x-text="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400"></p>
    @endif
</div>
