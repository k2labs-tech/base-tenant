<?php

declare(strict_types=1);

namespace Base\Tenant\Events;

use Base\Tenant\Models\OutboundWebhook;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An endpoint has reached `webhooks.failure_limit` consecutive failures.
 *
 * Raised once per episode: when the endpoint is switched off (`disable`), or
 * when it is first marked degraded (`degrade`). The package tells nobody on
 * its own; a product that owes its customers a warning listens for this.
 */
class WebhookEndpointFailing
{
    use Dispatchable;

    public const DISABLED = 'disable';

    public const DEGRADED = 'degrade';

    /**
     * @param  'disable'|'degrade'  $action
     */
    public function __construct(
        public readonly OutboundWebhook $webhook,
        public readonly string $action,
        public readonly int $failures,
    ) {}
}
