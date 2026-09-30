<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to tell one endpoint about one event.
 *
 * Extend it and point `base-tenant.webhooks.models.delivery` at the subclass;
 * the manager and the delivery job use the configured class.
 */
class OutboundWebhookDelivery extends Model
{
    use BelongsToAccount;
    use HasUuids;

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    /**
     * The 3.0 schedule, kept for compatibility.
     *
     * It was read as `BACKOFF[$attempt]` after the attempt counter had moved
     * on, so the first entry never applied: five attempts, with gaps of 5m,
     * 30m, 2h and 12h. That is exactly the default of
     * `base-tenant.webhooks.backoff` and `attempts`, which is what is in force.
     *
     * @deprecated 3.1.0 Read `WebhookManager::backoff()` and `attempts()` instead.
     */
    public const BACKOFF = [60, 300, 1800, 7200, 43200];

    protected $table = 'outbound_webhook_deliveries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(app(WebhookManager::class)->endpointModel(), 'outbound_webhook_id');
    }

    /**
     * The delivery this one repeats, when it was sent with `Webhook::redeliver()`.
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(static::class, 'redelivery_of');
    }

    public function isRedelivery(): bool
    {
        return $this->redelivery_of !== null;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempt < app(WebhookManager::class)->attempts();
    }

    /**
     * Seconds to wait before the next attempt, or null when there are none.
     */
    public function backoff(): ?int
    {
        return app(WebhookManager::class)->retryDelay((int) $this->attempt);
    }
}
