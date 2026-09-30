<?php

declare(strict_types=1);

namespace Base\Tenant\Connections\Webhooks;

use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;

/**
 * The shape of the body a receiver gets.
 *
 * Returns the structure, not the bytes: the job serialises it once and signs
 * and sends that same string, so a builder cannot make the signature and the
 * body disagree.
 *
 * Point `base-tenant.webhooks.payload_builder` at an implementation when a
 * product has a published contract with its own envelope.
 */
interface PayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook): array;
}
