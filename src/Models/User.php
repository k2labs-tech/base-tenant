<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Database\Factories\UserFactory;
use Base\Tenant\Exceptions\NoAccountException;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Traits\HasRolesAndPermissions;
use Base\Tenant\Traits\HasSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    use HasFactory;
    use HasRolesAndPermissions;
    use HasSettings;
    use HasUuids;
    use Impersonate;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            // Sin el cast llega como 1 y como '0', y `'0'` es una cadena no
            // vacía: cualquier comprobación escrita sin `(bool)` daría por
            // administrador de plataforma a quien no lo es.
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'accessed_at' => 'datetime',
            'decimal_places' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_required_from' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Laravel resolves factories by convention from the application namespace,
     * which never finds a package model. Naming it here lets a host
     * application call `factory()` on this model without any wiring.
     *
     * @var class-string<UserFactory>
     */
    protected static $factory = UserFactory::class;

    /**
     * Users reachable from an account, whether they were attached through the
     * pivot or only carry it as their primary account.
     */
    public function scopeInAccount(Builder $query, Account|string|null $account): Builder
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;

        if ($accountId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($accountId): void {
            $query->where('users.account_id', $accountId)
                ->orWhereHas('accounts', function (Builder $query) use ($accountId): void {
                    $query->where('accounts.id', $accountId);
                });
        });
    }

    /**
     * The primary account that belong to the user.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }

    /**
     * The accounts that belong to the user.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            config('base-tenant.models.account', Account::class)
        )->withTimestamps();
    }

    /**
     * Create primary account and set role for user.
     */
    public function createPrimaryAccountAndSetRole(
        ?string $accountName = null,
        string $userRole = 'customer-admin',
        bool $toCheckout = true
    ): Account {
        $accountClass = config('base-tenant.models.account', Account::class);

        $account = $accountClass::create([
            'name' => $accountName ?? $this->name."'s Team",
            'email' => $this->email,
            'user_id' => (string) $this->id,
            'trial_ends_at' => Carbon::now()->addDays(
                config('base-tenant.subscription.trial_days', 14)
            ),
        ]);

        $this->account_id = (string) $account->id;
        $this->accounts()->attach($account->id);
        $this->save();

        Tenant::runFor($account, function () use ($userRole): void {
            $this->addRole($userRole);
        });

        return $account;
    }

    /**
     * Get current team.
     */
    public function currentTeam(): Account
    {
        return $this->account;
    }

    /**
     * Get if the user is admin or not.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr(end($words), 0, 1));
        }

        preg_match_all('#([A-Z]+)#', $this->name, $capitals);

        if (count($capitals[1]) >= 2) {
            return substr(implode('', $capitals[1]), 0, 2);
        }

        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Get the user's initials (method version for compatibility).
     */
    public function initials(): string
    {
        return $this->initials; // Uses the accessor above
    }

    /**
     * Apply timezone to datetime.
     */
    public function applyTimeZone(mixed $dateTime): string
    {
        if ($this->timezone == '') {
            $this->timezone = config('app.timezone');
            $this->save();
        }

        return Carbon::parse($dateTime)
            ->timezone($this->timezone)
            ->format($this->date_format ?? 'd/m/Y');
    }

    /**
     * Apply datetime zone format.
     */
    public function applyDateTimeZoneFormat(
        mixed $dateTime = null,
        ?string $format = null,
        ?string $timezone = null
    ): string {
        $timezone = $timezone ?? $this->timezone ?? config('app.timezone');
        // `time_format`, not `hour_format`: there is no such column, so the
        // concatenation produced a date with a trailing space and no time at
        // all -- and the fallback below it never fired, because concatenating
        // a null yields an empty string rather than null.
        $format ??= trim(($this->date_format ?: 'd/m/Y').' '.($this->time_format ?: 'H:i:s'));

        return Carbon::parse($dateTime ?? now())->timezone($timezone)->format($format);
    }

    /**
     * Apply date format.
     */
    public function applyDateFormat(?string $date = null): string
    {
        if ($date != null) {
            return Carbon::parse($date)->format($this->date_format ?? 'd/m/Y');
        }

        return $this->date_format ?? 'd/m/Y';
    }

    /**
     * Apply currency format.
     */
    public function applyCurrencyFormat(float $amount, int $decimals = 2): string
    {
        $decimalSeparator = $this->decimals_separator ?: ',';
        $thousandsSeparator = $this->thousands_separator ?: ($decimalSeparator === ',' ? '.' : ',');

        return number_format($amount, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enableTwoFactorAuthentication(string $secret): void
    {
        $this->two_factor_secret = encrypt($secret);
        $this->two_factor_recovery_codes = $this->generateRecoveryCodes();
        $this->save();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactorAuthentication(): void
    {
        $this->two_factor_confirmed_at = now();
        $this->save();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactorAuthentication(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->two_factor_confirmed_at = null;
        $this->save();
    }

    /**
     * Generate recovery codes for two-factor authentication.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::upper(Str::random(10));
        }

        return $codes;
    }

    /**
     * Regenerate recovery codes for two-factor authentication.
     */
    public function regenerateRecoveryCodes(): void
    {
        $this->two_factor_recovery_codes = $this->generateRecoveryCodes();
        $this->save();
    }

    /**
     * Check if two-factor authentication is enabled and confirmed.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Get the decrypted two-factor secret.
     */
    public function getTwoFactorSecretAttribute(): ?string
    {
        return isset($this->attributes['two_factor_secret']) && $this->attributes['two_factor_secret']
            ? decrypt($this->attributes['two_factor_secret'])
            : null;
    }

    /**
     * Verify a recovery code and invalidate it.
     */
    public function invalidateRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes;
        $key = array_search($code, $codes);

        if ($key !== false) {
            unset($codes[$key]);
            $this->two_factor_recovery_codes = array_values($codes);
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Determine if the user can impersonate other users.
     * Only system admins can impersonate.
     */
    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin() || $this->can('users.impersonate');
    }

    /**
     * Determine if the user can be impersonated.
     * System admins cannot be impersonated.
     */
    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin() && ! is_null($this->account_id);
    }

    /**
     * Determine the default account for this user
     *
     * Priority: last_account_id > account_id > first account
     *
     * @throws NoAccountException
     */
    public function determineDefaultAccount(): string
    {
        // Priority: last_account_id > primary account_id > first account
        return $this->last_account_id
            ?? $this->account_id
            ?? $this->accounts()->first()?->id
            ?? throw Exceptions\NoAccountException::userHasNoAccounts();
    }

    /**
     * Determine if user should receive daily notification summaries.
     *
     * Cascading logic:
     * 1. If user has explicit preference (true/false), use that
     * 2. Otherwise, fall back to account's default setting
     * 3. If account has no setting, default to false
     */
    public function shouldReceiveDailySummary(): bool
    {
        // User explicit preference overrides account
        if ($this->daily_notification_summary !== null) {
            return (bool) $this->daily_notification_summary;
        }

        // Fall back to account default
        return (bool) ($this->account->daily_notification_summary ?? false);
    }
}
