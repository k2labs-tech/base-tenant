<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Database\Factories\AccountFactory;
use Base\Tenant\Services\FeatureService;
use Base\Tenant\Traits\HasSettings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Account extends Model
{
    use Billable, HasFactory, HasSettings, HasUuids, SoftDeletes;

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
     * Laravel resolves factories by convention from the application namespace,
     * which never finds a package model. Naming it here lets a host
     * application call `factory()` on this model without any wiring.
     *
     * @var class-string<AccountFactory>
     */
    protected static $factory = AccountFactory::class;

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
        return $this->owner?->email ?? $this->email;
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

    public function planCan(string $feature): bool
    {
        return FeatureService::accountCan($this, $feature);
    }

    public function planLimit(string $feature): int
    {
        return FeatureService::getLimit($this, $feature);
    }

    public function isWithinPlanLimit(string $feature, int $currentUsage): bool
    {
        return FeatureService::isWithinLimit($this, $feature, $currentUsage);
    }

    public function getPlanName(): string
    {
        return FeatureService::getAccountPlanName($this);
    }
}
