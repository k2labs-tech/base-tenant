<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One try at sending one delivery: what came back, or why nothing did.
 *
 * Written by the delivery job on every attempt while `webhooks.log_attempts`
 * is on. Extend it and point `base-tenant.webhooks.models.attempt` at the
 * subclass; the job and the relations use the configured class.
 */
class OutboundWebhookAttempt extends Model
{
    use BelongsToAccount;

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    /**
     * Held back by `webhooks.degraded_cooldown`. No request was made and no
     * attempt was spent: the row says when, and that the delivery is waiting.
     */
    public const POSTPONED = 'postponed';

    public $timestamps = false;

    protected $table = 'outbound_webhook_attempts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'attempted_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(app(WebhookManager::class)->deliveryModel(), 'delivery_id');
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(app(WebhookManager::class)->endpointModel(), 'outbound_webhook_id');
    }

    public function succeeded(): bool
    {
        return $this->outcome === self::DELIVERED;
    }
}
