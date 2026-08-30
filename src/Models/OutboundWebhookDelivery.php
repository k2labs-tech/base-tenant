<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to tell one endpoint about one event.
 */
class OutboundWebhookDelivery extends Model
{
    use BelongsToAccount;
    use HasUuids;

    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    /**
     * How long to wait before each retry.
     *
     * Widening gaps rather than a fixed interval: a receiver that is down is
     * usually down for minutes or hours, and hammering it every minute for a
     * day helps nobody. Five attempts spread over about fifteen hours covers
     * a deploy, an outage and a night.
     */
    public const BACKOFF = [60, 300, 1800, 7200, 43200];

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
        return $this->belongsTo(OutboundWebhook::class, 'outbound_webhook_id');
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempt < count(self::BACKOFF);
    }

    /**
     * Seconds to wait before the next attempt, or null when there are none.
     */
    public function backoff(): ?int
    {
        return self::BACKOFF[$this->attempt] ?? null;
    }
}
