<?php

declare(strict_types=1);

namespace Base\Tenant\Exceptions;

use Base\Tenant\Metering\Metric;
use Base\Tenant\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The account asked for more of something than its plan allows.
 *
 * 402 rather than 403: the request was understood and the caller is who they
 * say they are; what is missing is a bigger plan. The distinction matters to
 * API clients, which retry a 403 never and a 402 after an upgrade.
 */
class UsageLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly Metric $metric,
        public readonly int $current,
        public readonly int $limit,
        public readonly ?Account $account = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Usage limit reached for `%s`: %d of %d.',
            $metric->key,
            $current,
            $limit,
        ));
    }

    public static function for(Metric $metric, int $current, int $limit, ?Account $account = null): self
    {
        return new self($metric, $current, $limit, $account);
    }

    /**
     * The upgrade call to action lives here rather than in a handler, so a
     * host application that never touches its exception handler still gets a
     * screen that says what happened and where to go.
     */
    public function render(Request $request): SymfonyResponse
    {
        $payload = [
            'message' => __('base-tenant::metering.limit_reached', [
                'metric' => $this->label(),
            ]),
            'metric' => $this->metric->key,
            'current' => $this->current,
            'limit' => $this->limit,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, Response::HTTP_PAYMENT_REQUIRED);
        }

        return response()->view('base-tenant::errors.usage-limit', [
            ...$payload,
            'upgradeUrl' => $this->upgradeUrl(),
        ], Response::HTTP_PAYMENT_REQUIRED);
    }

    public function label(): string
    {
        return $this->metric->label
            ? __($this->metric->label)
            : __('base-tenant::metering.metrics.'.$this->metric->key);
    }

    /**
     * Where to go to fix it. Null when the host has subscriptions switched
     * off, in which case the screen says what happened and stops there rather
     * than offering a button that leads nowhere.
     */
    protected function upgradeUrl(): ?string
    {
        foreach (['base-tenant.billing', 'base-tenant.checkout'] as $route) {
            if (app('router')->has($route)) {
                return route($route);
            }
        }

        return null;
    }
}
