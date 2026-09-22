<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Base\Tenant\BaseTenantServiceProvider;

/**
 * Renders the service provider the application needs once the package code
 * lives inside it.
 *
 * The registrations are read from `BaseTenantServiceProvider`, not copied, so
 * a component added to the package is picked up here without anyone having to
 * remember this file exists.
 */
class ServiceProviderGenerator
{
    public function __construct(
        protected CodeTransformer $transformer,
        protected string $stubPath,
    ) {}

    public function render(): string
    {
        $namespace = $this->transformer->targetNamespace();
        $handle = $this->transformer->targetHandle();

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ handle }}' => $handle,
            '{{ singletons }}' => $this->renderList(BaseTenantServiceProvider::singletons()),
            '{{ bindings }}' => $this->renderMap(BaseTenantServiceProvider::bindings(), keysAreClasses: true),
            '{{ factories }}' => $this->renderFactories(BaseTenantServiceProvider::factorySingletons()),
            '{{ policies }}' => $this->renderMap(BaseTenantServiceProvider::policies()),
            '{{ middleware }}' => $this->renderMap(BaseTenantServiceProvider::middlewareAliases()),
            '{{ livewire }}' => $this->renderMap(BaseTenantServiceProvider::livewireComponents()),
            '{{ blade }}' => $this->renderMap($this->bladeComponents()),
            '{{ commands }}' => $this->renderList($this->commands()),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            file_get_contents($this->stubPath)
        );
    }

    /**
     * Class-based Blade components move from the package's `base-tenant::`
     * alias to a dotted one, matching how the anonymous components are
     * rewritten in the views.
     *
     * @return array<string, class-string>
     */
    protected function bladeComponents(): array
    {
        $handle = $this->transformer->targetHandle();
        $aliases = [];

        foreach (BaseTenantServiceProvider::bladeComponents() as $alias => $class) {
            $aliases[str_replace('base-tenant::', "{$handle}.", $alias)] = $class;
        }

        return $aliases;
    }

    /**
     * Package commands the application keeps: everything except the ones that
     * only make sense while the package is still a dependency.
     *
     * @return array<int, class-string>
     */
    protected function commands(): array
    {
        $excluded = array_map(
            static fn (string $path): string => basename($path, '.php'),
            array_filter(
                ScaffoldPlan::EXCLUDED_FROM_SRC,
                static fn (string $path): bool => str_starts_with($path, 'Console/Commands/')
            )
        );

        $commands = [];

        foreach (glob(dirname($this->stubPath).'/../src/Console/Commands/*.php') ?: [] as $file) {
            $class = basename($file, '.php');

            if (in_array($class, $excluded, true)) {
                continue;
            }

            $commands[] = "Base\\Tenant\\Console\\Commands\\{$class}";
        }

        sort($commands);

        return $commands;
    }

    /**
     * @param  array<int, class-string>  $classes
     */
    protected function renderList(array $classes, int $indent = 12): string
    {
        $pad = str_repeat(' ', $indent);

        return implode("\n", array_map(
            fn (string $class): string => $pad.'\\'.$this->transformer->transformNamespaces($class).'::class,',
            $classes
        ));
    }

    /**
     * @param  array<string, class-string>  $map
     */
    protected function renderMap(array $map, int $indent = 12, bool $keysAreClasses = false): string
    {
        $pad = str_repeat(' ', $indent);
        $lines = [];

        foreach ($map as $key => $class) {
            $left = $keysAreClasses
                ? '\\'.$this->transformer->transformNamespaces($key).'::class'
                : "'{$key}'";

            $lines[] = $pad.$left.' => \\'.$this->transformer->transformNamespaces($class).'::class,';
        }

        return implode("\n", $lines);
    }

    /**
     * Singletons built by a named constructor: the abstract is the class and
     * the value is the method that makes one.
     *
     * @param  array<class-string, string>  $factories
     */
    protected function renderFactories(array $factories, int $indent = 12): string
    {
        $pad = str_repeat(' ', $indent);
        $lines = [];

        foreach ($factories as $class => $method) {
            $lines[] = $pad.'\\'.$this->transformer->transformNamespaces($class)."::class => '{$method}',";
        }

        return implode("\n", $lines);
    }
}
