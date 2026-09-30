<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures\Tenancy\Unscoped;

use Illuminate\Database\Eloquent\Model;

/**
 * Holds account data without the trait: what the tenancy audit must catch.
 */
class LooseWidget extends Model
{
    protected $table = 'tenancy_widgets';

    protected $guarded = [];
}
