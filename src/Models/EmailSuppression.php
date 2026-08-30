<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One address nothing may be sent to.
 */
class EmailSuppression extends Model
{
    use HasUuids;

    public const BOUNCE = 'bounce';

    public const COMPLAINT = 'complaint';

    public const MANUAL = 'manual';

    public const UNSUBSCRIBE = 'unsubscribe';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'suppressed_at' => 'datetime',
        ];
    }

    /**
     * Always lower-cased on the way in. Comparing addresses case-sensitively
     * lets `Ada@Example.test` past a guard that holds `ada@example.test`.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }
}
