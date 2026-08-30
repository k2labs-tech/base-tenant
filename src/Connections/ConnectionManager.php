<?php

declare(strict_types=1);

namespace Base\Tenant\Connections;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\AccountConnection;
use Base\Tenant\Support\Module;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The external services one account is connected to.
 *
 *     Connection::for($account)->client('wubook');
 *     Connection::store('wubook', ['token' => '...']);
 *     Connection::check($connection);
 */
class ConnectionManager
{
    protected ?Account $account = null;

    public function for(Account|string|null $account): self
    {
        Module::ensure(Module::CONNECTIONS);

        if (is_string($account)) {
            $model = config('base-tenant.models.account', Account::class);
            $account = $model::find($account);
        }

        $clone = clone $this;
        $clone->account = $account;

        return $clone;
    }

    /**
     * The declared connectors, keyed by provider name.
     *
     * @return array<string, Connector>
     */
    public function connectors(): array
    {
        $connectors = [];

        foreach (config('base-tenant.connections.connectors', []) as $provider => $class) {
            if (! is_subclass_of($class, Connector::class) && ! in_array(Connector::class, class_implements($class) ?: [], true)) {
                throw new InvalidArgumentException("`{$class}` does not implement ".Connector::class.'.');
            }

            $connectors[$provider] = app($class);
        }

        return $connectors;
    }

    public function connector(string $provider): Connector
    {
        return $this->connectors()[$provider] ?? throw new InvalidArgumentException(
            "`{$provider}` is not a declared connector."
        );
    }

    public function supports(string $provider): bool
    {
        return array_key_exists($provider, $this->connectors());
    }

    /**
     * @return Collection<int, AccountConnection>
     */
    public function all(): Collection
    {
        return AccountConnection::query()
            ->forAccount($this->account())
            ->orderBy('provider')
            ->orderBy('label')
            ->get();
    }

    public function find(string $provider, string $label = 'default'): ?AccountConnection
    {
        return AccountConnection::query()
            ->forAccount($this->account())
            ->where('provider', $provider)
            ->where('label', $label)
            ->first();
    }

    /**
     * Whatever the application uses to talk to the provider.
     *
     * @throws RuntimeException when there is no usable connection
     */
    public function client(string $provider, string $label = 'default'): mixed
    {
        Module::ensure(Module::CONNECTIONS);

        $connection = $this->find($provider, $label);

        if (! $connection) {
            throw new RuntimeException("This account has no `{$provider}` connection labelled `{$label}`.");
        }

        if (! $connection->enabled) {
            throw new RuntimeException("The `{$provider}` connection labelled `{$label}` is switched off.");
        }

        return $this->connector($provider)->client($connection);
    }

    /**
     * Save credentials, replacing whatever was there under the same label.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function store(string $provider, array $credentials, string $label = 'default', array $metadata = []): AccountConnection
    {
        Module::ensure(Module::CONNECTIONS);

        $this->connector($provider);

        return AccountConnection::updateOrCreate(
            [
                'account_id' => $this->account()->getKey(),
                'provider' => $provider,
                'label' => $label,
            ],
            [
                'credentials' => $credentials,
                'metadata' => $metadata ?: null,
                'enabled' => true,

                // New credentials have not been checked yet, and carrying the
                // previous verdict forward would show a green light for a
                // token nobody has tried.
                'status' => AccountConnection::UNKNOWN,
                'status_message' => null,
                'checked_at' => null,
            ],
        );
    }

    public function forget(string $provider, string $label = 'default'): void
    {
        Module::ensure(Module::CONNECTIONS);

        $this->find($provider, $label)?->delete();
    }

    /**
     * Ask the provider whether a connection still works, and record it.
     *
     * A connector that throws is treated as unknown rather than failing: the
     * service being down does not mean the customer's credentials are wrong,
     * and telling them so is worse than saying nothing.
     */
    public function check(AccountConnection $connection): HealthCheck
    {
        try {
            $result = $this->connector($connection->provider)->healthCheck($connection);
        } catch (Throwable $exception) {
            $result = HealthCheck::unknown($exception->getMessage());
        }

        $connection->forceFill([
            'status' => $result->status,
            'status_message' => $result->message,
            'checked_at' => now(),
        ])->save();

        return $result;
    }

    protected function account(): Account
    {
        return $this->account
            ?? Tenant::current()
            ?? throw new RuntimeException('Connections need an account in context.');
    }
}
