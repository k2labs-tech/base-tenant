<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Traits\BelongsToAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An endpoint that wants to be told when something happens.
 *
 * Extend it and point `base-tenant.webhooks.models.endpoint` at the subclass
 * to add relations or behaviour; the manager and the delivery job use the
 * configured class.
 */
class OutboundWebhook extends Model
{
    use BelongsToAccount;
    use HasUuids;

    /**
     * Consecutive failures before the endpoint is acted on, by default.
     *
     * An endpoint that has been gone for days is not coming back on its own,
     * and every delivery to it costs a queued job and a timeout. The limit in
     * force is `base-tenant.webhooks.failure_limit`.
     *
     * @deprecated 3.1.0 Read `WebhookManager::failureLimit()` instead.
     */
    public const FAILURE_LIMIT = 20;

    protected $table = 'outbound_webhooks';

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'enabled' => 'boolean',
            'failure_count' => 'integer',
            'last_delivered_at' => 'datetime',
            'disabled_at' => 'datetime',
            'degraded_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(app(WebhookManager::class)->deliveryModel(), 'outbound_webhook_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Reached the failure limit under the `degrade` action: still receiving,
     * but somebody should look at it.
     */
    public function isDegraded(): bool
    {
        return $this->degraded_at !== null;
    }

    /**
     * Whether deliveries carry a signature. An endpoint without a secret is
     * only sent to when `base-tenant.webhooks.allow_unsigned` is on.
     */
    public function isSigned(): bool
    {
        return is_string($this->secret) && $this->secret !== '';
    }

    /**
     * Until when a degraded endpoint is left alone, under
     * `base-tenant.webhooks.degraded_cooldown`, or null when it is not paused.
     */
    public function pausedUntil(): ?CarbonInterface
    {
        return app(WebhookManager::class)->pausedUntil($this);
    }

    /**
     * Does this endpoint want this event?
     *
     * `*` takes everything; `booking.*` takes a family. Matching on a prefix
     * rather than only on exact names means a product can add
     * `booking.cancelled` without every subscriber having to be edited.
     */
    public function wants(string $event): bool
    {
        foreach ($this->events ?? [] as $pattern) {
            if ($pattern === '*' || $pattern === $event) {
                return true;
            }

            if (str_ends_with($pattern, '.*') && str_starts_with($event, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
