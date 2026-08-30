<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One navigation entry.
 *
 * Rows with a null `account_id` are the product menu, declared in code and
 * synced from it. Rows carrying an `account_id` either override one of those
 * for a single tenant or add an entry that only that tenant sees.
 */
class MenuItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'position' => 'integer',
            'route_params' => 'array',
            'meta' => 'array',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('account_id');
    }

    public function scopeForAccount(Builder $query, ?string $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function isOverride(): bool
    {
        return $this->account_id !== null && $this->is_system;
    }
}
