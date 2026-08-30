<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware;

use Base\Tenant\Facades\Meter;
use Base\Tenant\Support\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Gate a route on a metered allowance, and charge it.
 *
 *     Route::post('checks', ...)->middleware('base-tenant.metered:checks.run,3');
 *
 * The unit is taken before the work runs, not after. Checking first and
 * charging later is two statements with a gap in between, and two requests
 * arriving in that gap are both told there is room for the last unit. Taking
 * it first makes the decision and the charge the same locked statement, and a
 * request that then fails gets its unit back.
 */
class EnsureWithinUsageLimit
{
    public function handle(Request $request, Closure $next, string $metric, int|string $amount = 1): Response
    {
        if (! Module::enabled(Module::METERING)) {
            return $next($request);
        }

        $amount = (int) $amount;

        // Throws UsageLimitExceededException, which renders as a 402 with the
        // upgrade call to action.
        Meter::incrementOrFail($metric, $amount, [
            'metadata' => ['route' => $request->route()?->getName()],
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->refund($metric, $amount);

            throw $exception;
        }

        if ($response->getStatusCode() >= 400) {
            $this->refund($metric, $amount);
        }

        return $response;
    }

    /**
     * Give the unit back when the work it was taken for did not happen.
     *
     * The refund is a movement of its own rather than a deletion, so the
     * events still sum to the counter and the trail shows what was attempted.
     */
    protected function refund(string $metric, int $amount): void
    {
        Meter::decrement($metric, $amount, [
            'metadata' => ['refund' => true],
        ]);
    }
}
