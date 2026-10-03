<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookAttempt;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Send one delivery, and decide what happens if it does not arrive.
 *
 * The retries are managed here rather than by the queue's own `tries`: the
 * schedule is per delivery and visible in the row, so a customer can be shown
 * when the next attempt is due instead of being told it failed and left to
 * guess whether anything more will happen.
 *
 * Everything runs under the delivery's own account, whatever the worker or
 * the caller had in context: under strict tenancy a write to the delivery
 * with no account, or with another one, is refused.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * One, because the retry loop is this job re-queueing itself with the
     * delivery's own backoff. Letting the queue retry as well would produce
     * two overlapping schedules.
     */
    public int $tries = 1;

    /**
     * At least thirty seconds, and always longer than the HTTP timeout: a
     * worker killed mid-request leaves the delivery without its outcome.
     */
    public int $timeout = 30;

    public function __construct(public readonly string $deliveryId)
    {
        $this->timeout = max(30, app(WebhookManager::class)->timeout() + 15);
    }

    public function handle(WebhookManager $webhooks): void
    {
        $delivery = $webhooks->findDelivery($this->deliveryId);

        if (! $delivery || $delivery->status === OutboundWebhookDelivery::DELIVERED) {
            return;
        }

        Tenant::runFor((string) $delivery->account_id, function () use ($webhooks, $delivery): void {
            $this->deliver($webhooks, $delivery);
        });
    }

    protected function deliver(WebhookManager $webhooks, OutboundWebhookDelivery $delivery): void
    {
        $webhook = $webhooks->findEndpoint((string) $delivery->outbound_webhook_id);

        if (! $webhook || ! $webhook->enabled) {
            $delivery->update([
                'status' => OutboundWebhookDelivery::FAILED,
                'error' => 'The endpoint is no longer active.',
                'next_attempt_at' => null,
            ]);

            return;
        }

        if (! $webhook->isSigned() && ! $webhooks->allowsUnsigned()) {
            $delivery->update([
                'status' => OutboundWebhookDelivery::FAILED,
                'error' => __('base-tenant::connections.webhooks.unsigned_refused'),
                'next_attempt_at' => null,
            ]);

            return;
        }

        $pausedUntil = $this->runsInline() ? null : $webhooks->pausedUntil($webhook);

        if ($pausedUntil !== null) {
            $this->postpone($webhooks, $delivery, $pausedUntil);

            return;
        }

        $delivery->increment('attempt');

        // Serialised once and both signed and sent, so the receiver hashes
        // exactly the bytes we hashed. Re-encoding between the two is the
        // classic way a signature that is computed correctly still never
        // matches. Kept on the row, so later attempts and a redelivery send
        // the same request.
        $body = $webhooks->serialize($delivery, $webhook);

        if ($delivery->body === null) {
            $delivery->forceFill(['body' => $body])->save();
        }

        $headers = $webhooks->headers();

        $requestHeaders = [
            'Content-Type' => 'application/json',
            $headers['event'] => $delivery->event,
            $headers['delivery'] => $delivery->getKey(),
        ];

        // No secret, no signature header: an empty or made-up one would only
        // teach the receiver to skip verification.
        if ($webhook->isSigned()) {
            $requestHeaders[$headers['signature']] = $webhooks->sign($body, (string) $webhook->secret);
        }

        $attempt = (int) $delivery->attempt;
        $startedAt = hrtime(true);

        try {
            $response = Http::withHeaders($requestHeaders)
                ->withBody($body, 'application/json')
                ->timeout($webhooks->timeout())
                ->post($webhook->url);

            $duration = $this->elapsedMilliseconds($startedAt);

            if ($response->successful()) {
                $webhooks->recordAttempt($delivery, $attempt, OutboundWebhookAttempt::DELIVERED, $response->status(), null, $duration);

                $this->succeed($webhooks, $delivery, $webhook, $response->status(), $response->body());

                return;
            }

            $webhooks->recordAttempt($delivery, $attempt, OutboundWebhookAttempt::FAILED, $response->status(), null, $duration);

            $this->fail($webhooks, $delivery, $webhook, $response->status(), $response->body(), null);
        } catch (Throwable $exception) {
            $webhooks->recordAttempt(
                $delivery,
                $attempt,
                OutboundWebhookAttempt::FAILED,
                null,
                $exception->getMessage(),
                $this->elapsedMilliseconds($startedAt),
            );

            $this->fail($webhooks, $delivery, $webhook, null, null, $exception->getMessage());
        }
    }

    /**
     * Hold the delivery until the degraded endpoint's cooldown is over,
     * without spending an attempt. The delivery stays pending with
     * `next_attempt_at` set to the end of the pause; nothing is lost.
     */
    protected function postpone(WebhookManager $webhooks, OutboundWebhookDelivery $delivery, CarbonInterface $until): void
    {
        $delivery->update([
            'status' => OutboundWebhookDelivery::PENDING,
            'next_attempt_at' => $until,
        ]);

        $webhooks->recordAttempt(
            $delivery,
            (int) $delivery->attempt + 1,
            OutboundWebhookAttempt::POSTPONED,
            null,
            __('base-tenant::connections.webhooks.postponed', ['until' => $until->toIso8601String()]),
        );

        self::dispatch($delivery->getKey())->delay($until);
    }

    /**
     * A sync queue runs a delayed job at once, so a postponed delivery would
     * come straight back here, still paused, for ever. Inline, the cooldown
     * does not apply: there is no later to wait for.
     */
    protected function runsInline(): bool
    {
        return $this->job instanceof SyncJob;
    }

    protected function elapsedMilliseconds(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    protected function succeed(
        WebhookManager $webhooks,
        OutboundWebhookDelivery $delivery,
        OutboundWebhook $webhook,
        int $status,
        string $body,
    ): void {
        $delivery->update([
            'status' => OutboundWebhookDelivery::DELIVERED,
            'response_status' => $status,
            // Enough to debug with, not enough to turn the table into a log of
            // whatever the receiver felt like returning.
            'response_body' => mb_substr($body, 0, 2000),
            'error' => null,
            'next_attempt_at' => null,
            'delivered_at' => now(),
        ]);

        $webhooks->recordSuccess($webhook);
    }

    protected function fail(
        WebhookManager $webhooks,
        OutboundWebhookDelivery $delivery,
        OutboundWebhook $webhook,
        ?int $status,
        ?string $body,
        ?string $error,
    ): void {
        $delivery->refresh();

        $backoff = $delivery->backoff();

        $delivery->update([
            'status' => $backoff === null ? OutboundWebhookDelivery::FAILED : OutboundWebhookDelivery::PENDING,
            'response_status' => $status,
            'response_body' => $body === null ? null : mb_substr($body, 0, 2000),
            'error' => $webhooks->redactsErrors() ? $webhooks->redactError($error) : $error,
            'next_attempt_at' => $backoff === null ? null : now()->addSeconds($backoff),
        ]);

        $webhooks->recordFailedAttempt($webhook);

        if ($backoff !== null) {
            self::dispatch($delivery->getKey())->delay(now()->addSeconds($backoff));

            return;
        }

        // Out of attempts: the manager counts it against the endpoint and
        // decides whether the endpoint has failed for long enough to act on.
        $webhooks->recordFailure($webhook);
    }
}
