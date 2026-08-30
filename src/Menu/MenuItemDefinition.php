<?php

declare(strict_types=1);

namespace Base\Tenant\Menu;

use Closure;

/**
 * A navigation entry as declared in code. Modules build these; the manager
 * turns them into database rows.
 */
class MenuItemDefinition
{
    public ?string $label = null;

    public ?string $icon = null;

    public ?string $route = null;

    /** @var array<string, mixed> */
    public array $routeParams = [];

    public ?string $url = null;

    public ?string $target = null;

    public ?string $permission = null;

    public ?string $feature = null;

    public ?Closure $badgeResolver = null;

    public ?string $badge = null;

    public int $position = 0;

    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var array<int, self> */
    public array $children = [];

    public function __construct(public readonly string $key) {}

    /**
     * Translation key or literal text shown for the entry.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function route(string $route, array $params = []): self
    {
        $this->route = $route;
        $this->routeParams = $params;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function target(string $target): self
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Hide the entry unless the user holds this permission.
     */
    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Hide the entry unless the account has this feature enabled.
     */
    public function feature(string $feature): self
    {
        $this->feature = $feature;

        return $this;
    }

    /**
     * Counter shown next to the entry, resolved on every render.
     */
    public function badge(Closure|string $badge): self
    {
        $badge instanceof Closure
            ? $this->badgeResolver = $badge
            : $this->badge = $badge;

        return $this;
    }

    public function position(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Nest entries under this one.
     */
    public function children(Closure $callback): self
    {
        $builder = new MenuBuilder;

        $callback($builder);

        $this->children = $builder->all();

        return $this;
    }
}
