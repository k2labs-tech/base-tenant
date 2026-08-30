<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A single granular ability, such as `users.create`.
 *
 * Permissions are global by design: every account draws from the same
 * catalogue, and it is the roles that differ per tenant.
 */
class Permission extends SpatiePermission
{
    use HasFactory, HasUuids;

    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function getLabelAttribute(): string
    {
        return $this->display_name ?: $this->name;
    }

    /**
     * Every permission declared in configuration, keyed by group.
     *
     * @return Collection<string, array<int, string>>
     */
    public static function configured(): Collection
    {
        return collect(config('base-tenant.permissions', []))
            ->map(fn (array $permissions): array => array_values($permissions));
    }

    /** @return array<int, string> */
    public static function allConfiguredNames(): array
    {
        return static::configured()->flatten()->unique()->values()->all();
    }
}
