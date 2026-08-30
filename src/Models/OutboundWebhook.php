<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An endpoint that wants to be told when something happens.
 */
class OutboundWebhook extends Model
{
    use BelongsToAccount;
    use HasUuids;

    /**
     * Consecutive failures before the endpoint is switched off.
     *
     * An endpoint that has been gone for days is not coming back on its own,
     * and every delivery to it costs a queued job and a timeout.
     */
    public const FAILURE_LIMIT = 20;

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
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboundWebhookDelivery::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
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
