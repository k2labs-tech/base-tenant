<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

/**
 * Carries the active account across the queue boundary.
 *
 * The account id is stamped into every job payload when it is pushed and put
 * back in context when the worker picks the job up, so a queued job sees the
 * same tenant as the request that dispatched it.
 *
 * With `base-tenant.tenancy.restore_dispatch_context` on, the context a job
 * found is put back when it finishes instead of resetting the manager. That
 * matters for jobs run inline (the `sync` connection, `dispatchSync()`,
 * `afterResponse()`): without it, a job run inside `Tenant::runFor()` leaves
 * the rest of the block with no account.
 */
class QueueTenancy
{
    public const PAYLOAD_KEY = 'baseTenantAccountId';

    /**
     * Context each running job found, keyed by the job instance so nested
     * inline jobs each get their own frame back. Restored on JobAttempted,
     * the one event raised exactly once and last for every attempt: a failing
     * job raises both JobFailed and JobExceptionOccurred, in an order that
     * differs between the worker and the sync queue.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $saved = [];

    public static function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(static fn (): array => [
            self::PAYLOAD_KEY => Tenant::currentId(),
        ]);

        $events->listen(JobProcessing::class, static function (JobProcessing $event): void {
            if (static::restoresContext()) {
                static::$saved[spl_object_id($event->job)] = Tenant::captureState();
            }

            $accountId = $event->job->payload()[self::PAYLOAD_KEY] ?? null;

            $accountId === null
                ? Tenant::forget()
                : Tenant::set($accountId);
        });

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $event) {
            $events->listen($event, static function (): void {
                if (! static::restoresContext()) {
                    Tenant::reset();
                }
            });
        }

        $events->listen(JobAttempted::class, static function (JobAttempted $event): void {
            $key = spl_object_id($event->job);

            if (! isset(static::$saved[$key])) {
                return;
            }

            $state = static::$saved[$key];
            unset(static::$saved[$key]);

            Tenant::restoreState($state);
        });
    }

    protected static function restoresContext(): bool
    {
        return (bool) config('base-tenant.tenancy.restore_dispatch_context', false);
    }
}
