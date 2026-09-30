<?php

declare(strict_types=1);

namespace Base\Tenant\Connections\Webhooks;

use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;

/**
 * `{event, delivery, occurred_at, data}`.
 *
 * The delivery id travels inside the signed body, so a captured request cannot
 * be replayed against a different event.
 */
class DefaultPayloadBuilder implements PayloadBuilder
{
    public function build(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook): array
    {
        return [
            'event' => $delivery->event,
            'delivery' => $delivery->getKey(),
            'occurred_at' => $delivery->created_at?->toIso8601String(),
            'data' => $delivery->payload,
        ];
    }
}
