<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cache repository whose keys are namespaced by the active account, so two
 * tenants can never read each other's entries.
 */
class TenantCache
{
    public function __construct(protected Repository $store) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get(Tenant::cacheKey($key), $default);
    }

    public function put(string $key, mixed $value, DateTimeInterface|int|null $ttl = null): bool
    {
        return $this->store->put(Tenant::cacheKey($key), $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->store->forever(Tenant::cacheKey($key), $value);
    }

    public function remember(string $key, DateTimeInterface|int|null $ttl, Closure $callback): mixed
    {
        return $this->store->remember(Tenant::cacheKey($key), $ttl, $callback);
    }

    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->store->rememberForever(Tenant::cacheKey($key), $callback);
    }

    public function has(string $key): bool
    {
        return $this->store->has(Tenant::cacheKey($key));
    }

    public function forget(string $key): bool
    {
        return $this->store->forget(Tenant::cacheKey($key));
    }
}
