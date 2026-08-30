<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Send one delivery, and decide what happens if it does not arrive.
 *
 * The retries are managed here rather than by the queue's own `tries`: the
 * schedule is per delivery and visible in the row, so a customer can be shown
 * when the next attempt is due instead of being told it failed and left to
 * guess whether anything more will happen.
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

    public int $timeout = 30;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(WebhookManager $webhooks): void
    {
        $delivery = OutboundWebhookDelivery::query()->acrossAccounts()->find($this->deliveryId);

        if (! $delivery || $delivery->status === OutboundWebhookDelivery::DELIVERED) {
            return;
        }

        /** @var OutboundWebhook|null $webhook */
        $webhook = OutboundWebhook::query()->acrossAccounts()->find($delivery->outbound_webhook_id);

        if (! $webhook || ! $webhook->enabled) {
            $delivery->update([
                'status' => OutboundWebhookDelivery::FAILED,
                'error' => 'The endpoint is no longer active.',
                'next_attempt_at' => null,
            ]);

            return;
        }

        $delivery->increment('attempt');

        // Serialised once and both signed and sent, so the receiver hashes
        // exactly the bytes we hashed. Re-encoding between the two is the
        // classic way a signature that is computed correctly still never
        // matches.
        $body = (string) json_encode([
            'event' => $delivery->event,
            'delivery' => $delivery->getKey(),
            'occurred_at' => $delivery->created_at?->toIso8601String(),
            'data' => $delivery->payload,
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                WebhookManager::SIGNATURE_HEADER => $webhooks->sign($body, $webhook->secret),
                WebhookManager::EVENT_HEADER => $delivery->event,
                WebhookManager::DELIVERY_HEADER => $delivery->getKey(),
            ])->withBody($body, 'application/json')
                ->timeout(15)
                ->post($webhook->url);

            if ($response->successful()) {
                $this->succeed($delivery, $webhook, $response->status(), $response->body());

                return;
            }

            $this->fail($delivery, $webhook, $response->status(), $response->body(), null);
        } catch (Throwable $exception) {
            $this->fail($delivery, $webhook, null, null, $exception->getMessage());
        }
    }

    protected function succeed(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook, int $status, string $body): void
    {
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

        $webhook->forceFill([
            'failure_count' => 0,
            'last_delivered_at' => now(),
        ])->save();
    }

    protected function fail(
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
            'error' => $error,
            'next_attempt_at' => $backoff === null ? null : now()->addSeconds($backoff),
        ]);

        if ($backoff !== null) {
            self::dispatch($delivery->getKey())->delay(now()->addSeconds($backoff));

            return;
        }

        // Out of attempts. Count it against the endpoint, and switch the
        // endpoint off once it has been gone long enough that it is plainly
        // not coming back -- every delivery to a dead URL costs a queued job
        // and a timeout.
        $webhook->increment('failure_count');

        if ($webhook->fresh()?->failure_count >= OutboundWebhook::FAILURE_LIMIT) {
            $webhook->forceFill([
                'enabled' => false,
                'disabled_at' => now(),
            ])->save();
        }
    }
}
