<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One account's credentials for one external service.
 */
class AccountConnection extends Model
{
    use BelongsToAccount;
    use HasUuids;

    public const HEALTHY = 'healthy';

    public const FAILING = 'failing';

    public const UNKNOWN = 'unknown';

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            // Encrypted on write, decrypted on read: the plain value never
            // touches the column and never reaches a log or a dump.
            'credentials' => 'encrypted:array',
            'metadata' => 'array',
            'enabled' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function isHealthy(): bool
    {
        return $this->status === self::HEALTHY;
    }
}
