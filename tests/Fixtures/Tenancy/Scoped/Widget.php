<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures\Tenancy\Scoped;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant-owned model with soft deletes and a tenant-owned pivot, for the
 * strict tenancy tests. Its table is created by StrictTenancyFixtures.
 */
class Widget extends Model
{
    use BelongsToAccount;
    use SoftDeletes;

    protected $table = 'tenancy_widgets';

    protected $guarded = [];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tenancy_tag_widget', 'widget_id', 'tag_id');
    }
}
