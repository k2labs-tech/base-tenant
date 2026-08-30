<?php

declare(strict_types=1);

namespace Base\Tenant\Connections;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Jobs\DeliverWebhook;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Base\Tenant\Support\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Telling the outside world that something happened.
 *
 *     Webhook::dispatch('booking.confirmed', ['id' => $booking->id]);
 *
 * One call fans out to every endpoint of the account in context that asked
 * for the event. Nothing is sent inline: a receiver that takes eight seconds
 * to answer must not make the request that triggered it take eight seconds.
 */
class WebhookManager
{
    public const SIGNATURE_HEADER = 'X-BaseTenant-Signature';

    public const EVENT_HEADER = 'X-BaseTenant-Event';

    public const DELIVERY_HEADER = 'X-BaseTenant-Delivery';

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
            $delivery = OutboundWebhookDelivery::create([
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
     * The endpoints of one account that want this event.
     *
     * @return Collection<int, OutboundWebhook>
     */
    public function subscribers(string $event, string $accountId): Collection
    {
        return OutboundWebhook::query()
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

        return OutboundWebhook::create([
            'account_id' => $this->accountId($account),
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'secret' => Str::random(48),
            'enabled' => true,
        ]);
    }

    /**
     * The signature a receiver should compute to check a delivery.
     *
     * Over the exact bytes that are sent, and including the delivery id, so a
     * captured request cannot be replayed against a different event.
     */
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function verify(string $payload, string $secret, ?string $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        return hash_equals($this->sign($payload, $secret), $signature);
    }

    protected function accountId(Account|string|null $account): ?string
    {
        if ($account instanceof Account) {
            return (string) $account->getKey();
        }

        return $account ?? Tenant::currentId();
    }
}
