<?php

declare(strict_types=1);

namespace Base\Tenant\Menu;

/**
 * Collects the entries a module declares for a menu.
 */
class MenuBuilder
{
    /** @var array<string, MenuItemDefinition> */
    protected array $items = [];

    public function item(string $key): MenuItemDefinition
    {
        if (! isset($this->items[$key])) {
            $this->items[$key] = new MenuItemDefinition($key);
            $this->items[$key]->position = count($this->items) * 10;
        }

        return $this->items[$key];
    }

    /** @return array<int, MenuItemDefinition> */
    public function all(): array
    {
        return array_values($this->items);
    }
}
