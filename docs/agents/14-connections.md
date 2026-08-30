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

| Header | Meaning |
|---|---|
| `X-BaseTenant-Signature` | `hash_hmac('sha256', $rawBody, $secret)` |
| `X-BaseTenant-Event` | the event name |
| `X-BaseTenant-Delivery` | the delivery id, unique per attempt series |

The body is `{event, delivery, occurred_at, data}`. Verify against the **raw**
body: it is serialised once and both signed and sent, and re-encoding between
the two is the classic reason a correctly computed signature never matches.

The delivery id is inside the signed body, so a captured request cannot be
replayed against a different event.

### Retries

Five attempts at 1m, 5m, 30m, 2h and 12h. Widening gaps because a receiver
that is down is usually down for minutes or hours, and hammering it every
minute for a day helps nobody; the spread covers a deploy, an outage and a
night.

The schedule lives on the delivery row rather than in the queue's own retry
count, so a customer can be shown when the next attempt is due instead of being
told it failed and left to guess whether anything more will happen.

After 20 consecutive failures the endpoint is switched off. A URL that has been
gone for days is not coming back on its own, and every delivery to it costs a
queued job and a timeout.

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

`outbound_webhooks` — `url`, `events`, encrypted `secret`, `failure_count`.

`outbound_webhook_deliveries` — one row per event per endpoint, with its
attempt count, response and next attempt. Separate from the subscription so
that changing an endpoint's URL does not rewrite the history of what was sent
to it.
