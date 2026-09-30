# Connections and outbound webhooks

**Module:** M5 · **Package version:** v2 · **Last reviewed:** 2026-08-12
**Switches:** `BASE_TENANT_CONNECTIONS_ENABLED`, `BASE_TENANT_WEBHOOKS_ENABLED`

## When to use this

- Holding a customer's credentials for a third party they use: a channel
  manager, an accounting system, a mailbox.
- Telling an external system that something happened here.

## When NOT to use this

- The application's own API keys — those are `services.php` and belong to the
  installation, not to a customer.
- Signing a user in with Google. That is social login.
- A queued job that happens to make an HTTP call to a fixed URL you control.

---

## Connections

### Declare a connector

```php
use Base\Tenant\Connections\Connector;
use Base\Tenant\Connections\HealthCheck;

class WubookConnector implements Connector
{
    public function fields(): array
    {
        return [
            'token' => ['label' => 'app::wubook.token', 'type' => 'password', 'required' => true],
            'property' => ['label' => 'app::wubook.property', 'required' => true],
        ];
    }

    public function healthCheck(AccountConnection $connection): HealthCheck
    {
        $response = Http::withToken($connection->credentials['token'])->get('/ping');

        return $response->successful()
            ? HealthCheck::healthy()
            : HealthCheck::failing(__('app::wubook.rejected'));
    }

    public function client(AccountConnection $connection): mixed
    {
        return new WubookClient($connection->credentials['token']);
    }
}
```

Register it in `base-tenant.connections.connectors` under the provider name.
`fields()` draws the form, so adding a provider is a class and a config line —
the package never hard-codes anyone's inputs.

### Use it

```php
use Base\Tenant\Facades\Connection;

Connection::client('wubook');                       // account in context
Connection::for($account)->client('wubook', 'second-property');

Connection::store('wubook', ['token' => '...']);
Connection::find('wubook')?->isHealthy();
Connection::forget('wubook');
```

An account may hold two of the same provider, so `label` is part of the
identity and not decoration.

### Health

`k2labs-base:check-connections` runs nightly. A credential that expired three
weeks ago is otherwise discovered by a customer noticing nothing has synced.

Three outcomes, and the third matters: `healthy`, `failing`, and `unknown` for
"the provider could not be reached". A provider being down does not mean the
customer's credentials are wrong, and telling them so is worse than saying
nothing. A connector that throws is recorded as unknown, never as failing.

The owner is notified **on the transition into failing**, not on every check.
The first message is the news; the rest teach people to ignore them.

Storing new credentials resets the status to unknown: carrying the previous
verdict forward would show a green light for a token nobody has tried.

---

## Outbound webhooks

```php
use Base\Tenant\Facades\Webhook;

Webhook::dispatch('booking.confirmed', ['id' => $booking->id]);

Webhook::register('https://customer.example/hooks', ['booking.*'], name: 'PMS');
```

One call fans out to every endpoint of the account that asked for the event.
Nothing is sent inline: a receiver that takes eight seconds to answer must not
make the request that triggered it take eight seconds.

Subscriptions match `*`, an exact name, or a `booking.*` family — so a product
can add `booking.cancelled` without every subscriber being edited.

### What a receiver sees

| Header (default name) | Config key | Meaning |
|---|---|---|
| `X-BaseTenant-Signature` | `webhooks.headers.signature` | `signature_prefix` + `hash_hmac('sha256', $rawBody, $secret)` |
| `X-BaseTenant-Event` | `webhooks.headers.event` | the event name |
| `X-BaseTenant-Delivery` | `webhooks.headers.delivery` | the delivery id, unique per attempt series |

`signature_prefix` is empty by default; `'sha256='` gives the GitHub-style
`sha256=<hex>`. `Webhook::sign($body, $secret)` returns the full header value
and `Webhook::verify($body, $secret, $header)` compares it in constant time
(`hash_equals`).

The body is `{event, delivery, occurred_at, data}`, built by
`DefaultPayloadBuilder`. A product with its own published envelope points
`webhooks.payload_builder` at a class implementing `PayloadBuilder`:

```php
use Base\Tenant\Connections\Webhooks\PayloadBuilder;

class CheckPayload implements PayloadBuilder
{
    public function build(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook): array
    {
        return ['type' => $delivery->event, 'id' => $delivery->getKey(), 'data' => $delivery->payload];
    }
}
```

The builder returns an array; the job serialises it once, stores the bytes on
the delivery (`body`) and both signs and sends that string. Verify against the
**raw** body: re-encoding is the classic reason a correctly computed signature
never matches. Every retry sends the stored bytes, even if the builder changed
in between.

The delivery id is inside the default signed body, so a captured request cannot
be replayed against a different event.

### Retries

| Key | Default | Meaning |
|---|---|---|
| `webhooks.attempts` | `5` | attempts per delivery, the first included |
| `webhooks.backoff` | `[300, 1800, 7200, 43200]` | seconds to wait after each failed attempt; the last gap repeats if there are more attempts than entries |
| `webhooks.timeout` | `15` | HTTP timeout in seconds; the job's own timeout stays at least 15 s above it |

Widening gaps because a receiver that is down is usually down for minutes or
hours; the spread covers a deploy, an outage and a night. The 3.0 constant
`OutboundWebhookDelivery::BACKOFF` (deprecated) starts with a `60` that never
applied: the schedule in force was always the one above.

The schedule lives on the delivery row rather than in the queue's own retry
count, so a customer can be shown when the next attempt is due instead of being
told it failed and left to guess whether anything more will happen.

### Failing endpoints

After `webhooks.failure_limit` (20) consecutive failed deliveries the endpoint
is acted on, per `webhooks.on_failure_limit`:

- `disable` (default) — `enabled = false`, `disabled_at` set. It receives nothing
  more until someone switches it back on.
- `degrade` — stays enabled, `degraded_at` set (`isDegraded()`). The next
  successful delivery clears it and resets `failure_count`.

Either way `Base\Tenant\Events\WebhookEndpointFailing` (`webhook`, `action`,
`failures`) is raised once per episode. The package notifies nobody itself;
listen for it to warn the customer. `failure_limit => null` never acts.

### Redelivery

```php
$copy = Webhook::redeliver($delivery);
```

A new delivery row (`redelivery_of` = the original, `original()`,
`isRedelivery()`) with the original's exact body — the bytes it sent, or the
ones it would have sent — queued at once. The body therefore keeps the
original `delivery` id; the delivery header carries the new one.

### Models

`webhooks.models.endpoint` and `webhooks.models.delivery` name the classes the
manager, the job and the relations use. They must extend `OutboundWebhook` and
`OutboundWebhookDelivery`; anything else throws.

---

## Anti-patterns

```php
// ✗ The customer's API key in a settings row, in plain text, with no health
//   check and no way to tell which of their two properties it belongs to.
Settings::for($account)->set('wubook_token', $token);

// ✓
Connection::store('wubook', ['token' => $token], label: 'main-property');
```

```php
// ✗ Inline, unsigned, no retry. The request that triggered it now waits for a
//   third party, and a receiver that is down loses the event entirely.
Http::post($customer->webhook_url, $payload);

// ✓
Webhook::dispatch('booking.confirmed', $payload);
```

---

## Tables

`account_connections` — encrypted `credentials`, `status`, `checked_at`.
`UNIQUE (account_id, provider, label)`.

`outbound_webhooks` — `url`, `events`, encrypted `secret`, `failure_count`,
`disabled_at`, `degraded_at`.

`outbound_webhook_deliveries` — one row per event per endpoint, with its
attempt count, response, next attempt, the exact `body` sent and
`redelivery_of`. Separate from the subscription so
that changing an endpoint's URL does not rewrite the history of what was sent
to it.
