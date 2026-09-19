<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Database\Factories\UserSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One open session of one user.
 *
 * Not `BelongsToAccount`: a session belongs to a person, and in multi-team mode
 * the same session moves between accounts as they switch. `account_id` records
 * which account was active when it was last seen, which is useful context but
 * is not what owns the row.
 *
 * @property string $session_id
 */
class UserSession extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'last_active_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @var class-string<UserSessionFactory> */
    protected static $factory = UserSessionFactory::class;

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('base-tenant.models.user', User::class));
    }

    /**
     * @param  Builder<UserSession>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * The framework's session id is a bearer credential. It is hashed on the
     * way in and never stored or displayed in the clear.
     */
    public static function fingerprint(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    public function isCurrent(?string $sessionId = null): bool
    {
        $sessionId ??= session()->getId();

        return $this->session_id === self::fingerprint($sessionId);
    }
}
