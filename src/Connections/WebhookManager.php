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
use Base\Tenant\Models\OutboundWebhookDelivery;
use Base\Tenant\Support\Module;
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

            DeliverWebhook::dispatch($delivery->getKey());

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

        DeliverWebhook::dispatch($copy->getKey());

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
     * @param  list<string>  $events
     */
    public function register(string $url, array $events = ['*'], ?string $name = null, Account|string|null $account = null): OutboundWebhook
    {
        Module::ensure(Module::WEBHOOKS);

        return $this->endpointModel()::create([
            'account_id' => $this->accountId($account),
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'secret' => Str::random(48),
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

        return (string) json_encode($this->payloadBuilder()->build($delivery, $webhook));
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

    public function findEndpoint(string $id): ?OutboundWebhook
    {
        return $this->endpointModel()::query()->acrossAccounts()->find($id);
    }

    public function findDelivery(string $id): ?OutboundWebhookDelivery
    {
        return $this->deliveryModel()::query()->acrossAccounts()->find($id);
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
