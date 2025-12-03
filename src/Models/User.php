<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Livewire\Features\SupportRedirects\Redirector;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Impersonate, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'timezone',
        'locale',
        'currency',
        'decimal_places',
        'decimals_separator',
        'thousands_separator',
        'date_format',
        'time_format',
        'account_id',
        'last_account_id',
        'must_change_password',
    ];

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
            'accessed_at' => 'datetime',
            'decimal_places' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('base-tenant.models.role', Role::class)
        );
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
    ): Account|Redirector {
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
        $this->addRole($userRole);
        $this->save();

        return $account;
    }

    /**
     * The alerts that belong to the user.
     */
    public function hasAlerts(): int
    {
        return random_int(0, 1);
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
        return $this->admin;
    }

    /**
     * Return true or false if the Role has been assigned properly.
     */
    public function addRole(?string $role = null): bool
    {
        if (! $role) {
            return false;
        }

        $roleClass = config('base-tenant.models.role', Role::class);
        $roleId = $roleClass::where('key', $role)->value('id');

        if (! $roleId) {
            return false;
        }

        try {
            $this->roles()->attach($roleId);

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Store the user roles in session (filtered by current account if applicable).
     */
    public function storeRolesSession(): void
    {
        $currentAccountId = session('current_account_id');

        if ($currentAccountId) {
            // Filter roles by current account_id in pivot table
            // This gets roles where account_id matches OR is null (global roles)
            $roles = $this->roles()
                ->where(function ($query) use ($currentAccountId) {
                    $query->where('role_user.account_id', $currentAccountId)
                          ->orWhereNull('role_user.account_id');
                })
                ->pluck('key')
                ->toArray();
        } else {
            // If no current account, get all roles
            $roles = $this->roles->pluck('key')->toArray();
        }

        session(['user.roles' => $roles]);
    }

    /**
     * Check if the user has any of the roles.
     */
    public function authorizeRoles(array|string $roles): bool
    {
        abort_unless($this->hasAnyRole($roles), 401);

        return true;
    }

    /**
     * Check if the user has any of the roles.
     */
    public function hasAnyRole(array|string $roles): bool
    {
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }
        } else {
            if ($this->hasRole($roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        if (! session()->has('user.roles')) {
            $this->storeRolesSession();
        }

        return in_array($role, session()->get('user.roles', []));
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
        $format = $format ?? ($this->date_format.' '.$this->hour_format) ?? 'd/m/Y H:i:s';

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
        $thousandsPointer = $this->decimals_pointer == ',' ? '.' : ',';

        return number_format($amount, $decimals, $thousandsPointer, $this->decimals_pointer);
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
        return $this->is_admin || is_null($this->account_id);
    }

    /**
     * Determine if the user can be impersonated.
     * System admins cannot be impersonated.
     */
    public function canBeImpersonated(): bool
    {
        return ! ($this->is_admin || is_null($this->account_id));
    }

    /**
     * Determine the default account for this user
     *
     * Priority: last_account_id > account_id > first account
     *
     * @throws \Base\Tenant\Exceptions\NoAccountException
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
     *
     * @return bool
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
