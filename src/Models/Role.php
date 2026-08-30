<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role, either global (`account_id` is null) or owned by a single account.
 *
 * `name` is the identifier spatie/laravel-permission works with, `key` is kept
 * as a synced alias for backwards compatibility, and `display_name` holds the
 * label shown in the interface.
 */
class Role extends SpatieRole
{
    use HasExtensibleRoles, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * `key` mirrors `name` and `display_name` falls back to it.
     *
     * Done with a mutator rather than a `saving` listener because a model event
     * can be silenced: Laravel's own `DatabaseSeeder` ships with
     * `WithoutModelEvents`, and under it `key` — which is NOT NULL — was never
     * populated and seeding died.
     */
    protected function name(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'name' => $value,
            'key' => $value,
            'display_name' => $this->attributes['display_name'] ?? $value,
        ]);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }

    /**
     * Roles that can be assigned in the account currently in context: the
     * global catalogue plus whatever the account defined for itself.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        $accountId = Tenant::currentId();

        return $query->where(function (Builder $query) use ($accountId): void {
            $query->whereNull('account_id');

            if ($accountId !== null) {
                $query->orWhere('account_id', $accountId);
            }
        });
    }

    /**
     * Roles the role editor shows.
     *
     * The global catalogue belongs to the product: a tenant cannot change it,
     * so listing it there only offers actions that end in a denial. Staff see
     * everything assignable, everyone else sees the roles their own account
     * defined.
     */
    public function scopeManageable(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $query->assignable();
        }

        return $query->whereNotNull('account_id')
            ->where('account_id', Tenant::currentId());
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeNonSystem(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function isGlobal(): bool
    {
        return $this->account_id === null;
    }

    /**
     * Label for the interface, falling back to the identifier.
     */
    public function getLabelAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }
}
