<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures\Tenancy\Scoped;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * A child of Widget in the same account, for the join tests.
 */
class Gadget extends Model
{
    use BelongsToAccount;

    protected $table = 'tenancy_gadgets';

    public $timestamps = false;

    protected $guarded = [];
}
