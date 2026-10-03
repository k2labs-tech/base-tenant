<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Connections\Webhooks\PayloadBuilder;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookAttempt;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, OutboundWebhookDelivery> dispatch(string $event, array $payload = [], Account|string|null $account = null)
 * @method static Collection<int, OutboundWebhook> subscribers(string $event, string $accountId)
 * @method static OutboundWebhook register(string $url, array $events = ['*'], string|null $name = null, Account|string|null $account = null, bool $signed = true)
 * @method static string sign(string $payload, string $secret)
 * @method static bool verify(string $payload, string $secret, string|null $signature)
 * @method static OutboundWebhookDelivery redeliver(OutboundWebhookDelivery $delivery)
 * @method static string serialize(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook)
 * @method static PayloadBuilder payloadBuilder()
 * @method static array{signature: string, event: string, delivery: string} headers()
 * @method static string signaturePrefix()
 * @method static int attempts()
 * @method static list<int> backoff()
 * @method static int|null retryDelay(int $attempt)
 * @method static int timeout()
 * @method static int|null failureLimit()
 * @method static string failureAction()
 * @method static void recordFailure(OutboundWebhook $webhook)
 * @method static void recordSuccess(OutboundWebhook $webhook)
 * @method static class-string<OutboundWebhook> endpointModel()
 * @method static class-string<OutboundWebhookDelivery> deliveryModel()
 * @method static class-string<OutboundWebhookAttempt> attemptModel()
 * @method static int jsonFlags()
 * @method static bool allowsUnsigned()
 * @method static bool logsAttempts()
 * @method static bool redactsErrors()
 * @method static int|null degradedCooldown()
 * @method static CarbonInterface|null pausedUntil(OutboundWebhook $webhook)
 * @method static string|null redactError(string|null $error)
 * @method static OutboundWebhookAttempt|null recordAttempt(OutboundWebhookDelivery $delivery, int $attempt, string $outcome, int|null $statusCode = null, string|null $error = null, int|null $durationMs = null)
 * @method static void recordFailedAttempt(OutboundWebhook $webhook)
 * @method static OutboundWebhook|null findEndpoint(string $id)
 * @method static OutboundWebhookDelivery|null findDelivery(string $id)
 *
 * @see WebhookManager
 */
class Webhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebhookManager::class;
    }
}
