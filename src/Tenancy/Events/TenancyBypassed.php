<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The account scope was switched off on purpose.
 *
 * Crossing accounts is sometimes the job -- staff screens, maintenance
 * commands, a lookup that has to happen before the owner is known -- but it
 * should never go unrecorded. Listen to this event and write it wherever the
 * host keeps its audit trail.
 *
 * Only the outermost `Tenant::runWithout()` frame is announced: a nested one
 * changes nothing and would bury the entry that names the real caller.
 */
class TenancyBypassed
{
    use Dispatchable;

    /** `Tenant::runWithout()`: every query in the callback runs unscoped. */
    public const SOURCE_RUN_WITHOUT = 'run_without';

    /** `->acrossAccounts()`: one query runs unscoped. */
    public const SOURCE_ACROSS_ACCOUNTS = 'across_accounts';

    /** `->forAccount($other)`: one query reads a named account. */
    public const SOURCE_FOR_ACCOUNT = 'for_account';

    /**
     * Frames skipped when looking for the caller: the layer itself and the
     * framework plumbing between the caller and the layer.
     */
    private const INTERNAL_PATH_FRAGMENTS = [
        '/src/Tenancy/',
        '/src/Traits/BelongsToAccount.php',
        '/src/Facades/Tenant.php',
        '/Illuminate/',
    ];

    /**
     * @param  string|null  $accountId  the account in context when the scope was switched off
     * @param  string  $source  one of the SOURCE_* constants
     * @param  string|null  $reason  free text the caller passed, when it did
     * @param  string|null  $callSite  `file:line` of the first frame outside the layer
     * @param  string|null  $userId  the authenticated user, when one is resolved
     * @param  string|null  $subject  the model queried, for query-level bypasses
     * @param  string|null  $targetAccountId  the account read, for `forAccount()`
     */
    public function __construct(
        public readonly ?string $accountId,
        public readonly string $source,
        public readonly ?string $reason = null,
        public readonly ?string $callSite = null,
        public readonly ?string $userId = null,
        public readonly ?string $subject = null,
        public readonly ?string $targetAccountId = null,
    ) {}

    /**
     * Build the event with the call site and user filled in, and dispatch it.
     */
    public static function record(
        ?string $accountId,
        string $source,
        ?string $reason = null,
        ?string $subject = null,
        ?string $targetAccountId = null,
    ): void {
        if (! app()->bound('events')) {
            return;
        }

        app('events')->dispatch(new self(
            accountId: $accountId,
            source: $source,
            reason: $reason,
            callSite: self::resolveCallSite(),
            userId: self::resolveUserId(),
            subject: $subject,
            targetAccountId: $targetAccountId,
        ));
    }

    private static function resolveCallSite(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null) {
                continue;
            }

            $normalised = str_replace('\\', '/', $file);

            foreach (self::INTERNAL_PATH_FRAGMENTS as $fragment) {
                if (str_contains($normalised, $fragment)) {
                    continue 2;
                }
            }

            return $file.':'.($frame['line'] ?? 0);
        }

        return null;
    }

    /**
     * Never resolves a user on its own: asking the guard to authenticate from
     * inside a query could recurse into the very query being audited.
     */
    private static function resolveUserId(): ?string
    {
        try {
            if (! app()->bound('auth')) {
                return null;
            }

            $guard = Auth::guard();

            if (! $guard->hasUser()) {
                return null;
            }

            $id = $guard->id();

            return $id === null ? null : (string) $id;
        } catch (Throwable) {
            return null;
        }
    }
}
