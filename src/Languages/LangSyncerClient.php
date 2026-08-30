<?php

declare(strict_types=1);

namespace Base\Tenant\Languages;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The HTTP half of the LangSyncer integration.
 *
 * ---------------------------------------------------------------------------
 * ASSUMED CONTRACT. Everything about the endpoints and payload shapes below is
 * inferred from the specification, not from LangSyncer's documentation. It has
 * not been exercised against the real service.
 *
 * The rest of the integration -- the commands, the webhook, the signature
 * check, writing the language files, flushing the caches -- does not depend on
 * these details. When the real API is known, this class is the only thing that
 * changes.
 *
 *   POST /api/projects/{project}/keys   {"keys": {"file.key": "source text"}}
 *        -> {"created": 12, "updated": 3}
 *
 *   GET  /api/projects/{project}/translations?locale=ca
 *        -> {"translations": {"file.key": "text"}}
 *
 *   GET  /api/projects/{project}/locales
 *        -> {"locales": ["en", "es", "ca"]}
 * ---------------------------------------------------------------------------
 */
class LangSyncerClient
{
    public function configured(): bool
    {
        return $this->key() !== '' && $this->project() !== '';
    }

    /**
     * Send the source keys upstream.
     *
     * @param  array<string, string>  $keys
     * @return array<string, mixed>
     */
    public function push(array $keys): array
    {
        return $this->request()
            ->post("/api/projects/{$this->project()}/keys", ['keys' => $keys])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, string>
     */
    public function pull(string $locale): array
    {
        $payload = $this->request()
            ->get("/api/projects/{$this->project()}/translations", ['locale' => $locale])
            ->throw()
            ->json();

        return $payload['translations'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        $payload = $this->request()
            ->get("/api/projects/{$this->project()}/locales")
            ->throw()
            ->json();

        return $payload['locales'] ?? [];
    }

    /**
     * Is this webhook really from LangSyncer?
     *
     * `hash_equals` and not `===`: comparing signatures with a short-circuiting
     * comparison leaks, through timing, how much of the prefix was right.
     */
    public function verifySignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('base-tenant.languages.langsyncer.webhook_secret');

        if ($secret === '' || $signature === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    protected function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'LangSyncer is not configured. Set LANGSYNCER_API_KEY and LANGSYNCER_PROJECT.'
            );
        }

        return Http::baseUrl((string) config('base-tenant.languages.langsyncer.url'))
            ->withToken($this->key())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }

    protected function key(): string
    {
        return (string) config('base-tenant.languages.langsyncer.key');
    }

    protected function project(): string
    {
        return (string) config('base-tenant.languages.langsyncer.project');
    }
}
