<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures\Tenancy\Scoped;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToAccount;

    protected $table = 'tenancy_tags';

    public $timestamps = false;

    protected $guarded = [];
}
