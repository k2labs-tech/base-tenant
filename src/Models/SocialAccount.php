<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One external identity linked to one user.
 *
 * Not scoped to an account: the link is to the person, and the same person may
 * belong to several accounts.
 */
class SocialAccount extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            // Laravel encrypts on write and decrypts on read, so the plain
            // value never touches the column and never reaches a log.
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.user', User::class)
        );
    }
}
