<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasExtensibleRoles, HasFactory, HasUuids;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * The users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('base-tenant.models.user', User::class)
        );
    }

    /**
     * Scope a query to only include non-system roles.
     */
    public function scopeNonSystem(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope a query to only include system roles.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }
}
