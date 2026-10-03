<?php

declare(strict_types=1);

namespace Base\Tenant\Connections;

use Base\Tenant\Connections\Webhooks\DefaultPayloadBuilder;
use Base\Tenant\Connections\Webhooks\PayloadBuilder;
use Base\Tenant\Events\WebhookEndpointFailing;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Jobs\DeliverWebhook;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookAttempt;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Base\Tenant\Support\Module;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Telling the outside world that something happened.
 *
 *     Webhook::dispatch('booking.confirmed', ['id' => $booking->id]);
 *
 * One call fans out to every endpoint of the account in context that asked
 * for the event. Nothing is sent inline: a receiver that takes eight seconds
 * to answer must not make the request that triggered it take eight seconds.
 *
 * Everything a receiver can see -- header names, the signature format, the
 * envelope, the retry schedule -- is read from `base-tenant.webhooks`, with
 * the defaults below. A product with a published webhook contract changes the
 * configuration, not this class.
 */
class WebhookManager
{
    /**
     * Default header names. The ones actually sent come from `headers()`.
     */
    public const SIGNATURE_HEADER = 'X-BaseTenant-Signature';

    public const EVENT_HEADER = 'X-BaseTenant-Event';

    public const DELIVERY_HEADER = 'X-BaseTenant-Delivery';

    /**
     * Seconds to wait after each failed attempt: after the first, after the
     * second, and so on. Widening gaps, because a receiver that is down is
     * usually down for minutes or hours; the spread covers a deploy, an
     * outage and a night.
     */
    public const DEFAULT_BACKOFF = [300, 1800, 7200, 43200];

    public const DEFAULT_ATTEMPTS = 5;

    public const DEFAULT_TIMEOUT = 15;

    public const DEFAULT_FAILURE_LIMIT = 20;

    /**
     * Queue a delivery to every subscribed endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, OutboundWebhookDelivery>
     */
    public function dispatch(string $event, array $payload = [], Account|string|null $account = null): Collection
    {
        if (! Module::enabled(Module::WEBHOOKS)) {
            return new Collection;
        }

        $accountId = $this->accountId($account);

        if ($accountId === null) {
            return new Collection;
        }

        $deliveries = new Collection;

        foreach ($this->subscribers($event, $accountId) as $webhook) {
            $delivery = $this->deliveryModel()::create([
                'account_id' => $accountId,
                'outbound_webhook_id' => $webhook->getKey(),
                'event' => $event,
                'payload' => $payload,
                'status' => OutboundWebhookDelivery::PENDING,
            ]);

            $this->queue($delivery);

            $deliveries->push($delivery);
        }

        return $deliveries;
    }

    /**
     * Send a delivery again, as a new delivery with the exact same body.
     *
     * The original row is left as it was: it is the record of what happened.
     * The new one carries the original's bytes -- the ones it sent, or the
     * ones it would have sent if it never got that far -- so the receiver
     * gets the same signed body. The delivery header carries the new id.
     */
    public function redeliver(OutboundWebhookDelivery $delivery): OutboundWebhookDelivery
    {
        Module::ensure(Module::WEBHOOKS);

        $body = $delivery->body;

        if ($body === null) {
            $webhook = $this->findEndpoint((string) $delivery->outbound_webhook_id);

            $body = $webhook === null ? null : $this->serialize($delivery, $webhook);
        }

        $copy = $this->deliveryModel()::create([
            'account_id' => $delivery->account_id,
            'outbound_webhook_id' => $delivery->outbound_webhook_id,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'body' => $body,
            'redelivery_of' => $delivery->getKey(),
            'status' => OutboundWebhookDelivery::PENDING,
        ]);

        $this->queue($copy);

        return $copy;
    }

    /**
     * The endpoints of one account that want this event.
     *
     * @return Collection<int, OutboundWebhook>
     */
    public function subscribers(string $event, string $accountId): Collection
    {
        return $this->endpointModel()::query()
            ->forAccount($accountId)
            ->enabled()
            ->get()
            ->filter(fn (OutboundWebhook $webhook): bool => $webhook->wants($event))
            ->values();
    }

    /**
     * Register an endpoint. The secret is generated here rather than accepted:
     * a secret the caller chose is a secret somebody typed.
     *
     * `signed: false` registers it without a secret, so deliveries go out
     * with no signature header. Only with `webhooks.allow_unsigned` on.
     *
     * @param  list<string>  $events
     *
     * @throws InvalidArgumentException for an unsigned endpoint while `allow_unsigned` is off
     */
    public function register(
        string $url,
        array $events = ['*'],
        ?string $name = null,
        Account|string|null $account = null,
        bool $signed = true,
    ): OutboundWebhook {
        Module::ensure(Module::WEBHOOKS);

        if (! $signed && ! $this->allowsUnsigned()) {
            throw new InvalidArgumentException('Unsigned webhook endpoints are off: set base-tenant.webhooks.allow_unsigned to register one.');
        }

        return $this->endpointModel()::create([
            'account_id' => $this->accountId($account),
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'secret' => $signed ? Str::random(48) : null,
            'enabled' => true,
        ]);
    }

    /**
     * The signature header value for a body: the configured prefix followed
     * by the hex HMAC-SHA256 of those exact bytes.
     *
     * Over the exact bytes that are sent, and including the delivery id, so a
     * captured request cannot be replayed against a different event.
     */
    public function sign(string $payload, string $secret): string
    {
        return $this->signaturePrefix().hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Check a signature header value against a raw body, in constant time.
     */
    public function verify(string $payload, string $secret, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload, $secret), $signature);
    }

    /**
     * The body a delivery sends, serialised once.
     *
     * A delivery that already holds its bytes -- a redelivery, or any attempt
     * after the first -- sends those, so every attempt is the same request.
     */
    public function serialize(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook): string
    {
        if ($delivery->body !== null) {
            return $delivery->body;
        }

        return (string) json_encode($this->payloadBuilder()->build($delivery, $webhook), $this->jsonFlags());
    }

    /**
     * Flags for the one `json_encode()` that produces a body, e.g.
     * `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`. Default 0, the 3.1
     * encoding. Only the first attempt encodes: later attempts and
     * redeliveries send the stored bytes, so changing the flags never makes
     * a retry differ from what was signed the first time.
     */
    public function jsonFlags(): int
    {
        return (int) $this->config('json_flags', 0);
    }

    /**
     * Whether an endpoint without a secret is sent to, with no signature
     * header. Off by default: an endpoint with no secret is refused, as in 3.1.
     */
    public function allowsUnsigned(): bool
    {
        return (bool) $this->config('allow_unsigned', false);
    }

    /**
     * Whether every attempt is written to `outbound_webhook_attempts`.
     */
    public function logsAttempts(): bool
    {
        return (bool) $this->config('log_attempts', true);
    }

    /**
     * Whether URLs in error messages are cut down to their origin before
     * they are stored.
     */
    public function redactsErrors(): bool
    {
        return (bool) $this->config('redact_errors', false);
    }

    /**
     * Seconds a degraded endpoint is left alone after its last failed
     * attempt, or null when degraded endpoints keep receiving (3.1).
     */
    public function degradedCooldown(): ?int
    {
        $seconds = (int) $this->config('degraded_cooldown', 0);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Until when the endpoint is paused, or null when it may be sent to now.
     *
     * Only a degraded endpoint is paused, and only for `degraded_cooldown`
     * seconds after its last failed attempt (or after it was marked degraded,
     * for an endpoint that failed before 3.2 recorded the time).
     */
    public function pausedUntil(OutboundWebhook $webhook): ?CarbonInterface
    {
        $cooldown = $this->degradedCooldown();

        if ($cooldown === null || ! $webhook->isDegraded()) {
            return null;
        }

        $since = $webhook->last_failed_at ?? $webhook->degraded_at;

        if ($since === null) {
            return null;
        }

        $until = $since->copy()->addSeconds($cooldown);

        return $until->isFuture() ? $until : null;
    }

    /**
     * An error message as it is stored: every URL in it cut down to scheme,
     * host and port when `redact_errors` is on, and at most 2000 characters.
     *
     * The HTTP client puts the whole request URI in a connection error, and
     * an endpoint URL is often a credential in itself -- a token in the path
     * or the query, a user and password before the host.
     */
    public function redactError(?string $error): ?string
    {
        if ($error === null) {
            return null;
        }

        if ($this->redactsErrors()) {
            $error = (string) preg_replace_callback(
                '~\b[a-z][a-z0-9+.\-]*://[^\s"\'<>]+~i',
                static function (array $match): string {
                    $parts = parse_url($match[0]);

                    if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
                        return '[redacted]';
                    }

                    $port = isset($parts['port']) ? ':'.$parts['port'] : '';

                    return "{$parts['scheme']}://{$parts['host']}{$port}/[redacted]";
                },
                $error,
            );
        }

        return mb_substr($error, 0, 2000);
    }

    /**
     * Write one attempt to the history, when it is kept.
     *
     * @param  OutboundWebhookAttempt::DELIVERED|OutboundWebhookAttempt::FAILED|OutboundWebhookAttempt::POSTPONED  $outcome
     */
    public function recordAttempt(
        OutboundWebhookDelivery $delivery,
        int $attempt,
        string $outcome,
        ?int $statusCode = null,
        ?string $error = null,
        ?int $durationMs = null,
    ): ?OutboundWebhookAttempt {
        if (! $this->logsAttempts()) {
            return null;
        }

        return $this->attemptModel()::create([
            'account_id' => $delivery->account_id,
            'delivery_id' => $delivery->getKey(),
            'outbound_webhook_id' => $delivery->outbound_webhook_id,
            'attempt' => $attempt,
            'outcome' => $outcome,
            'status_code' => $statusCode,
            'error' => $this->redactError($error),
            'duration_ms' => $durationMs,
            'attempted_at' => now(),
        ]);
    }

    public function payloadBuilder(): PayloadBuilder
    {
        $class = (string) $this->config('payload_builder', DefaultPayloadBuilder::class);

        $builder = app($class);

        if (! $builder instanceof PayloadBuilder) {
            throw new InvalidArgumentException("[{$class}] must implement ".PayloadBuilder::class.'.');
        }

        return $builder;
    }

    /**
     * @return array{signature: string, event: string, delivery: string}
     */
    public function headers(): array
    {
        $headers = (array) $this->config('headers', []);

        return [
            'signature' => (string) ($headers['signature'] ?? self::SIGNATURE_HEADER),
            'event' => (string) ($headers['event'] ?? self::EVENT_HEADER),
            'delivery' => (string) ($headers['delivery'] ?? self::DELIVERY_HEADER),
        ];
    }

    public function signaturePrefix(): string
    {
        return (string) $this->config('signature_prefix', '');
    }

    /**
     * Total attempts per delivery, the first one included.
     */
    public function attempts(): int
    {
        return max(1, (int) $this->config('attempts', self::DEFAULT_ATTEMPTS));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $backoff = array_values(array_map('intval', (array) $this->config('backoff', self::DEFAULT_BACKOFF)));

        return $backoff === [] ? self::DEFAULT_BACKOFF : $backoff;
    }

    /**
     * Seconds to wait after the given attempt fails, or null when it was the
     * last one. A schedule shorter than the attempts repeats its last gap.
     */
    public function retryDelay(int $attempt): ?int
    {
        if ($attempt >= $this->attempts()) {
            return null;
        }

        $backoff = $this->backoff();

        return $backoff[max($attempt, 1) - 1] ?? $backoff[array_key_last($backoff)];
    }

    /**
     * Seconds the HTTP request may take.
     */
    public function timeout(): int
    {
        return max(1, (int) $this->config('timeout', self::DEFAULT_TIMEOUT));
    }

    /**
     * Consecutive failed deliveries before the endpoint is acted on, or null
     * when the configuration says never.
     */
    public function failureLimit(): ?int
    {
        $limit = config()->has('base-tenant.webhooks.failure_limit')
            ? config('base-tenant.webhooks.failure_limit')
            : self::DEFAULT_FAILURE_LIMIT;

        return $limit === null || (int) $limit < 1 ? null : (int) $limit;
    }

    /**
     * What happens at the failure limit: `disable` switches the endpoint off,
     * `degrade` marks it and keeps sending.
     *
     * @return 'disable'|'degrade'
     */
    public function failureAction(): string
    {
        $action = $this->config('on_failure_limit', WebhookEndpointFailing::DISABLED);

        if (! in_array($action, [WebhookEndpointFailing::DISABLED, WebhookEndpointFailing::DEGRADED], true)) {
            throw new InvalidArgumentException(sprintf(
                "base-tenant.webhooks.on_failure_limit must be 'disable' or 'degrade', [%s] given.",
                is_scalar($action) ? (string) $action : get_debug_type($action),
            ));
        }

        return $action;
    }

    /**
     * Count a delivery that ran out of attempts against its endpoint, and act
     * once the endpoint has been gone long enough that it is plainly not
     * coming back -- every delivery to a dead URL costs a queued job and a
     * timeout.
     *
     * The event is raised once per episode: an endpoint already switched off,
     * or already marked degraded, is not announced again.
     */
    public function recordFailure(OutboundWebhook $webhook): void
    {
        $webhook->increment('failure_count');

        $webhook = $webhook->fresh() ?? $webhook;

        $limit = $this->failureLimit();

        if ($limit === null || $webhook->failure_count < $limit) {
            return;
        }

        if ($this->failureAction() === WebhookEndpointFailing::DISABLED) {
            if (! $webhook->enabled) {
                return;
            }

            $webhook->forceFill([
                'enabled' => false,
                'disabled_at' => now(),
            ])->save();

            WebhookEndpointFailing::dispatch($webhook, WebhookEndpointFailing::DISABLED, $webhook->failure_count);

            return;
        }

        if ($webhook->degraded_at !== null) {
            return;
        }

        $webhook->forceFill(['degraded_at' => now()])->save();

        WebhookEndpointFailing::dispatch($webhook, WebhookEndpointFailing::DEGRADED, $webhook->failure_count);
    }

    /**
     * One attempt did not arrive. Kept apart from `recordFailure()`, which
     * counts deliveries that ran out of attempts: the degraded cooldown runs
     * from the last failed attempt, not from the last exhausted delivery.
     */
    public function recordFailedAttempt(OutboundWebhook $webhook): void
    {
        $webhook->forceFill(['last_failed_at' => now()])->save();
    }

    /**
     * A delivery arrived: the endpoint's failure streak, and any degraded
     * mark it earned, are over.
     */
    public function recordSuccess(OutboundWebhook $webhook): void
    {
        $webhook->forceFill([
            'failure_count' => 0,
            'degraded_at' => null,
            'last_delivered_at' => now(),
        ])->save();
    }

    /**
     * @return class-string<OutboundWebhook>
     */
    public function endpointModel(): string
    {
        return $this->model('endpoint', OutboundWebhook::class);
    }

    /**
     * @return class-string<OutboundWebhookDelivery>
     */
    public function deliveryModel(): string
    {
        return $this->model('delivery', OutboundWebhookDelivery::class);
    }

    /**
     * @return class-string<OutboundWebhookAttempt>
     */
    public function attemptModel(): string
    {
        return $this->model('attempt', OutboundWebhookAttempt::class);
    }

    public function findEndpoint(string $id): ?OutboundWebhook
    {
        return $this->endpointModel()::query()->acrossAccounts()->find($id);
    }

    public function findDelivery(string $id): ?OutboundWebhookDelivery
    {
        return $this->deliveryModel()::query()->acrossAccounts()->find($id);
    }

    /**
     * Queue the job under the delivery's own account.
     *
     * The queue stamps the account in context into the job, and the caller
     * may have none -- a console command, a scheduled task, a listener
     * passing the account explicitly -- or another one. The block function
     * is deliberate: an arrow function would return the PendingDispatch,
     * which pushes only once the block has restored the outer context.
     */
    protected function queue(OutboundWebhookDelivery $delivery): void
    {
        $accountId = (string) $delivery->account_id;

        if (Tenant::currentId() === $accountId) {
            DeliverWebhook::dispatch($delivery->getKey());

            return;
        }

        Tenant::runFor($accountId, function () use ($delivery): void {
            DeliverWebhook::dispatch($delivery->getKey());
        });
    }

    protected function accountId(Account|string|null $account): ?string
    {
        if ($account instanceof Account) {
            return (string) $account->getKey();
        }

        return $account ?? Tenant::currentId();
    }

    /**
     * A configured model has to extend the package's: the job and the manager
     * rely on its constants, casts and scopes.
     *
     * @template T of object
     *
     * @param  class-string<T>  $default
     * @return class-string<T>
     */
    protected function model(string $key, string $default): string
    {
        $class = (string) $this->config("models.{$key}", $default);

        if (! is_a($class, $default, true)) {
            throw new InvalidArgumentException("base-tenant.webhooks.models.{$key} must extend {$default}, [{$class}] given.");
        }

        return $class;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("base-tenant.webhooks.{$key}") ?? $default;
    }
}
