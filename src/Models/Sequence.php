<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One correlative counter.
 *
 * Never written through Eloquent from outside SequenceManager: handing out a
 * number is a locked read followed by a write, and any other path would give
 * two callers the same one.
 */
class Sequence extends Model
{
    use HasUuids;

    /** The `account_id` value used by sequences that are not per account. */
    public const GLOBAL = 'global';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}
