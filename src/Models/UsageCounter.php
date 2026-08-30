<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The running total of one metric, for one account, in one period.
 *
 * Nothing writes to this model through Eloquent: the value is moved by a
 * single atomic statement in UsageStore, because two requests incrementing at
 * once through read-modify-write would lose one of the two movements.
 */
class UsageCounter extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'notified_threshold' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }
}
