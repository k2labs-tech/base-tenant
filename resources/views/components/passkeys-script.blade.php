{{-- The two WebAuthn ceremonies, once per page. The profile registers a
     passkey and the login screen asserts one; the base64url plumbing, the
     CSRF header and the shape of the payload the server expects are the same
     for both, and the usual reason a first passkey implementation silently
     fails is two copies of that plumbing drifting apart. --}}
@once
    @push('scripts')
        <script data-navigate-once>
            window.baseTenantPasskeys = window.baseTenantPasskeys || (() => {
                /* base64url in and out: the browser speaks ArrayBuffer, the
                   server speaks the spec's base64url. */
                const decode = (value) => {
                    const padded = value.replace(/-/g, '+').replace(/_/g, '/');
                    const raw = atob(padded + '='.repeat((4 - padded.length % 4) % 4));
                    return Uint8Array.from(raw, c => c.charCodeAt(0));
                };

                const encode = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer)))
                    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

                const headers = () => ({
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    'Accept': 'application/json',
                });

                /* A refusal from the server surfaces as an error named
                   `ServerError` carrying its message, so callers can tell it
                   from the person closing the system dialog. */
                const post = async (url, body) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: headers(),
                        body: body === undefined ? undefined : JSON.stringify(body),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const error = new Error(data.message ?? '');
                        error.name = 'ServerError';
                        throw error;
                    }

                    return data;
                };

                return {
                    supported: () => Boolean(window.PublicKeyCredential),

                    async register(optionsUrl, registerUrl, name) {
                        const options = await post(optionsUrl);

                        options.challenge = decode(options.challenge);
                        options.user.id = decode(options.user.id);
                        (options.excludeCredentials || []).forEach(c => c.id = decode(c.id));

                        const credential = await navigator.credentials.create({ publicKey: options });

                        return post(registerUrl, {
                            name,
                            credential: JSON.stringify({
                                id: credential.id,
                                rawId: encode(credential.rawId),
                                type: credential.type,
                                response: {
                                    clientDataJSON: encode(credential.response.clientDataJSON),
                                    attestationObject: encode(credential.response.attestationObject),
                                },
                            }),
                        });
                    },

                    async login(optionsUrl, loginUrl) {
                        const options = await post(optionsUrl);

                        options.challenge = decode(options.challenge);
                        (options.allowCredentials || []).forEach(c => c.id = decode(c.id));

                        const credential = await navigator.credentials.get({ publicKey: options });

                        return post(loginUrl, {
                            credential: JSON.stringify({
                                id: credential.id,
                                rawId: encode(credential.rawId),
                                type: credential.type,
                                response: {
                                    clientDataJSON: encode(credential.response.clientDataJSON),
                                    authenticatorData: encode(credential.response.authenticatorData),
                                    signature: encode(credential.response.signature),
                                    userHandle: credential.response.userHandle ? encode(credential.response.userHandle) : null,
                                },
                            }),
                        });
                    },
                };
            })();
        </script>
    @endpush
@endonce
