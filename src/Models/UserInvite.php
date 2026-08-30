<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvite extends Model
{
    use BelongsToAccount;
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.role', Role::class)
        );
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.user', User::class),
            'invited_by'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Caducada es solo la que nadie aceptó: una invitación aceptada hace meses
     * también tiene la fecha pasada, y no es lo mismo.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }
}
