<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Database\Factories\MagicLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in link, good for one use before it expires.
 *
 * @property string $token
 */
class MagicLink extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /** @var class-string<MagicLinkFactory> */
    protected static $factory = MagicLinkFactory::class;

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('base-tenant.models.user', User::class));
    }

    /**
     * Usable right now: not spent, not expired.
     *
     * This scope is the guard. Both halves matter and both have their own
     * test: without the first, a link works forever once it has been mailed;
     * without the second, an old link in a mailbox is a permanent key.
     *
     * @param  Builder<MagicLink>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * The token is a credential. It is hashed on the way in and the raw value
     * exists only in the email that was sent.
     */
    public static function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
