<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Account extends Model
{
    use Billable, HasFactory, HasUuids, SoftDeletes;

    /**
     * Get the attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'force_password_change' => 'boolean',
        'onboarded_at' => 'datetime',
    ];

    /**
     * Get the users that belong to the account.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('base-tenant.models.user', User::class)
        );
    }

    /**
     * Get the owner of the account.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.user', User::class),
            'user_id'
        );
    }

    /**
     * Get the customer email that should be synced to Stripe.
     */
    public function stripeEmail(): ?string
    {
        return $this->owner->email;
    }

    /**
     * Get the customer name that should be synced to Stripe.
     */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * Return if the account has active subscriptions by counting the active subscriptions.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->active()->count() > 0;
    }
}
