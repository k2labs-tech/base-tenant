<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvite extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'account_id',
        'role',
        'token',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * The account this invitation belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }

    /**
     * Scope: pending invitations (not yet used).
     */
    public function scopePending($query)
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope: used invitations.
     */
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Scope: invitations for a specific account.
     */
    public function scopeForAccount($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Mark this invitation as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Check if this invitation has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Generate a unique token.
     */
    public static function generateToken(): string
    {
        return Str::random(50);
    }

    /**
     * Get the registration URL with token.
     */
    public function getRegisterUrl(): string
    {
        return url('/register?invite='.$this->token);
    }

    /**
     * Find a pending invite by token.
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->pending()->first();
    }

    /**
     * Check if a token exists but has already been used.
     */
    public static function isTokenUsed(string $token): bool
    {
        return static::where('token', $token)->used()->exists();
    }
}
