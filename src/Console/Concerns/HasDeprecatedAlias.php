<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Keeps the v1 `base-tenant:*` name working while the canonical name moves to
 * `k2labs-base:*`.
 *
 * The alias is a real Symfony alias rather than a forwarding command, so the
 * old name resolves to this exact class with this exact signature: a host's
 * deploy script keeps working byte for byte, and there is no second definition
 * of the arguments that could drift from the first one.
 *
 * The notice is emitted from `run()` and not from `handle()` so that no command
 * can forget to announce its own deprecation.
 */
trait HasDeprecatedAlias
{
    public function __construct()
    {
        parent::__construct();

        $this->setAliases([$this->deprecatedAlias()]);
    }

    /**
     * The v1 name. Derived, because every command was renamed the same way.
     */
    protected function deprecatedAlias(): string
    {
        return str_replace('k2labs-base:', 'base-tenant:', (string) $this->getName());
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getFirstArgument() === $this->deprecatedAlias()) {
            $output->writeln(sprintf(
                '<comment>%s</comment>',
                __('base-tenant::console.deprecated_alias', [
                    'alias' => $this->deprecatedAlias(),
                    'command' => (string) $this->getName(),
                ])
            ));
        }

        return parent::run($input, $output);
    }
}
