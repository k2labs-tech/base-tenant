<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, OutboundWebhookDelivery> dispatch(string $event, array $payload = [], Account|string|null $account = null)
 * @method static Collection<int, OutboundWebhook> subscribers(string $event, string $accountId)
 * @method static OutboundWebhook register(string $url, array $events = ['*'], string|null $name = null, Account|string|null $account = null)
 * @method static string sign(string $payload, string $secret)
 * @method static bool verify(string $payload, string $secret, string|null $signature)
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
