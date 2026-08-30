<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Illuminate\Contracts\Events\Dispatcher;
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
 */
class QueueTenancy
{
    public const PAYLOAD_KEY = 'baseTenantAccountId';

    public static function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(static fn (): array => [
            self::PAYLOAD_KEY => Tenant::currentId(),
        ]);

        $events->listen(JobProcessing::class, static function (JobProcessing $event): void {
            $accountId = $event->job->payload()[self::PAYLOAD_KEY] ?? null;

            $accountId === null
                ? Tenant::forget()
                : Tenant::set($accountId);
        });

        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $event) {
            $events->listen($event, static function (): void {
                Tenant::reset();
            });
        }
    }
}
