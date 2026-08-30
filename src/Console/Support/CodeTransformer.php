<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

/**
 * Rewrites package code so it can live inside the host application.
 *
 * Three kinds of reference get rewritten:
 *
 * - PHP namespaces and class strings, `Base\Tenant\` becoming `App\`
 * - Blade component tags, `<x-base-tenant::foo>` becoming `<x-tenant.foo>`
 * - View and translation namespaces, `base-tenant::` becoming `tenant::`
 *
 * Views and translations keep a namespace instead of being dumped into
 * `resources/views` and `lang` directly: the application owns the files either
 * way, and a namespace means nothing of yours is ever silently shadowed.
 */
class CodeTransformer
{
    public function __construct(
        protected string $sourceNamespace = 'Base\\Tenant',
        protected string $targetNamespace = 'App',
        protected string $sourceHandle = 'base-tenant',
        protected string $targetHandle = 'tenant',
    ) {}

    public function transform(string $contents): string
    {
        $contents = $this->transformNamespaces($contents);
        $contents = $this->transformBladeComponents($contents);

        return $this->transformViewAndTranslationNamespaces($contents);
    }

    /**
     * Prefix replacements, applied longest first.
     *
     * Factories, seeders and tests do not live under the application
     * namespace: a standard Laravel `composer.json` maps `Database\Factories\`
     * to `database/factories/` and `Tests\` to `tests/`, so putting them under
     * `App\` would leave them unautoloadable.
     *
     * @return array<string, string>
     */
    public function namespaceMap(): array
    {
        return [
            "{$this->sourceNamespace}\\Database" => 'Database',
            "{$this->sourceNamespace}\\Tests" => 'Tests',
            $this->sourceNamespace => $this->targetNamespace,
        ];
    }

    /**
     * `Base\Tenant\Models\User` becomes `App\Models\User`, in code and in the
     * double-escaped string form used by configuration files.
     *
     * A leading root separator is left alone, so `\Base\Tenant\Models\User`
     * stays fully qualified rather than turning into a relative reference.
     */
    public function transformNamespaces(string $contents): string
    {
        foreach ($this->namespaceMap() as $source => $target) {
            $patterns = [
                '/'.preg_quote(str_replace('\\', '\\\\', $source), '/').'(?![A-Za-z0-9_])/',
                '/'.preg_quote($source, '/').'(?![A-Za-z0-9_])/',
            ];

            $replacements = [str_replace('\\', '\\\\', $target), $target];

            $contents = preg_replace($patterns, $replacements, $contents);
        }

        return $contents;
    }

    /**
     * Both the tag and its closing form, including self-closing tags.
     */
    public function transformBladeComponents(string $contents): string
    {
        $handle = preg_quote($this->sourceHandle, '/');

        return preg_replace(
            "/<(\\/?)x-{$handle}::/",
            "<$1x-{$this->targetHandle}.",
            $contents
        );
    }

    /**
     * Every remaining `base-tenant::` names either a view or a translation
     * key -- the prefix has no other meaning -- so a blanket replacement is
     * both correct and safer than trying to enumerate the callers that can
     * introduce one. Component tags are already gone by this point.
     */
    public function transformViewAndTranslationNamespaces(string $contents): string
    {
        return str_replace("{$this->sourceHandle}::", "{$this->targetHandle}::", $contents);
    }

    public function targetNamespace(): string
    {
        return $this->targetNamespace;
    }

    public function targetHandle(): string
    {
        return $this->targetHandle;
    }
}
