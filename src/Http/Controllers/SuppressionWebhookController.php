<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Support\Module;
use Base\Tenant\Suppressions\SuppressionDriver;
use Base\Tenant\Suppressions\SuppressionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Bounces and complaints arriving from the mail provider.
 */
class SuppressionWebhookController extends Controller
{
    public function __invoke(Request $request, string $driver, SuppressionManager $suppressions): JsonResponse
    {
        abort_unless(Module::enabled(Module::SUPPRESSIONS), 404);

        $handler = $this->driver($driver);

        if (! $handler->verify($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        foreach ($handler->extract($request) as $entry) {
            $suppressions->suppress(
                $entry['email'],
                $entry['reason'],
                $driver,
                $entry['metadata'] ?? [],
            );
        }

        // 200 and not 202: the work is done by the time this returns, and
        // Mailgun retries anything else for days.
        return response()->json(['message' => 'Recorded.']);
    }

    protected function driver(string $driver): SuppressionDriver
    {
        $declared = config('base-tenant.suppressions.drivers', []);

        abort_unless(array_key_exists($driver, $declared), 404);

        $handler = app($declared[$driver]);

        if (! $handler instanceof SuppressionDriver) {
            throw new InvalidArgumentException("`{$driver}` is not a ".SuppressionDriver::class.'.');
        }

        return $handler;
    }
}
