<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Closure;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

/**
 * The bus dispatcher, made to push each job under the context it was built in.
 *
 * `Job::dispatch()` queues nothing by itself: it returns a PendingDispatch
 * that pushes when it is destroyed. Two ordinary shapes push outside the
 * context they were written in:
 *
 * - `Tenant::runFor($a, fn () => Job::dispatch())` returns the PendingDispatch,
 *   so it is destroyed after `runFor()` has restored the outer context, and
 *   the payload carries the wrong account. TenantManager records the block's
 *   context against the job; this dispatcher puts it back around the push.
 * - `dispatch($job)->afterResponse()` runs the job when the application
 *   terminates, by which time the context may have moved on. The context at
 *   the moment of dispatch is captured and restored around that run.
 *
 * Enabled with `base-tenant.tenancy.restore_dispatch_context`.
 */
class TenantAwareBusDispatcher extends Dispatcher
{
    /**
     * Swap the framework's dispatcher for this one. `extend()` and not a
     * binding, because the bus provider is deferred and would overwrite a
     * binding made before it loads.
     */
    public static function register(Container $app): void
    {
        $app->extend(Dispatcher::class, static fn (mixed $dispatcher, Container $app): self => new self(
            $app,
            static fn ($connection = null) => $app->make(QueueFactory::class)->connection($connection),
        ));
    }

    /**
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command)
    {
        return $this->underDispatchingContext($command, fn (): mixed => parent::dispatch($command));
    }

    /**
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return void
     */
    public function dispatchAfterResponse($command, $handler = null)
    {
        $tenancy = $this->container->make(TenantManager::class);

        $state = (is_object($command) ? $tenancy->pullDeferredDispatchState($command) : null)
            ?? $tenancy->captureState();

        if (! $this->allowsDispatchingAfterResponses) {
            $tenancy->runWithState($state, fn (): mixed => $this->dispatchSync($command, $handler));

            return;
        }

        $this->container->terminating(function () use ($tenancy, $state, $command, $handler): void {
            $tenancy->runWithState($state, fn (): mixed => $this->dispatchSync($command, $handler));
        });
    }

    protected function underDispatchingContext(mixed $command, Closure $push): mixed
    {
        if (! is_object($command)) {
            return $push();
        }

        $tenancy = $this->container->make(TenantManager::class);
        $state = $tenancy->pullDeferredDispatchState($command);

        return $state === null ? $push() : $tenancy->runWithState($state, $push);
    }
}
