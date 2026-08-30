<?php

declare(strict_types=1);

namespace Base\Tenant\Connections;

use Base\Tenant\Models\AccountConnection;

/**
 * How the application talks to one external service.
 *
 *     class WubookConnector implements Connector
 *     {
 *         public function fields(): array
 *         {
 *             return [
 *                 'token' => ['label' => 'app::wubook.token', 'type' => 'password', 'required' => true],
 *                 'property' => ['label' => 'app::wubook.property', 'type' => 'text', 'required' => true],
 *             ];
 *         }
 *
 *         public function healthCheck(AccountConnection $connection): HealthCheck
 *         {
 *             $response = Http::withToken($connection->credentials['token'])->get('...');
 *
 *             return $response->successful()
 *                 ? HealthCheck::healthy()
 *                 : HealthCheck::failing('The token was rejected.');
 *         }
 *
 *         public function client(AccountConnection $connection): mixed
 *         {
 *             return new WubookClient($connection->credentials['token']);
 *         }
 *     }
 *
 * `fields()` is what draws the form: the package never hard-codes a
 * provider's inputs, so adding one is a class and a config line.
 */
interface Connector
{
    /**
     * The credentials this provider needs.
     *
     * @return array<string, array{label: string, type?: string, required?: bool, help?: string}>
     */
    public function fields(): array;

    /**
     * Ask the provider whether these credentials still work.
     *
     * Must not throw: a provider that is down is a failing check, not an
     * exception that stops the nightly run for every other account.
     */
    public function healthCheck(AccountConnection $connection): HealthCheck;

    /**
     * Whatever the application uses to talk to the provider.
     */
    public function client(AccountConnection $connection): mixed;
}
