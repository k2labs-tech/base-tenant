<?php

declare(strict_types=1);

namespace Base\Tenant\Files;

use InvalidArgumentException;

/**
 * The rules of one named group of files: what it accepts, how big, how many,
 * and which renditions are derived from it.
 *
 * Declared on the model rather than passed at the call site, so the answer to
 * "may this file go here" is the same whether it arrives from the uploader, an
 * import or a command.
 */
final class FileCollection
{
    /**
     * `public` is for files meant to be seen by anyone with the link -- the
     * logo on a status page, an avatar in a public profile. Their files get a
     * stable address from `File::publicUrl()` that needs no session and does
     * not expire. Everything else stays private, which is the default.
     *
     * @param  list<string>  $accepts  MIME patterns; `image/*` matches a family
     * @param  array<string, array{width?: int, height?: int, fit?: string}>  $variants
     */
    public function __construct(
        public readonly string $name,
        public readonly array $accepts = [],
        public readonly ?int $maxSize = null,
        public readonly bool $single = false,
        public readonly array $variants = [],
        public readonly bool $public = false,
    ) {}

    /**
     * @param  list<string>  $accepts
     * @param  array<string, array{width?: int, height?: int, fit?: string}>  $variants
     */
    public static function make(
        string $name,
        array $accepts = [],
        ?int $maxSize = null,
        bool $single = false,
        array $variants = [],
        bool $public = false,
    ): self {
        return new self($name, $accepts, $maxSize, $single, $variants, $public);
    }

    /**
     * A collection from its entry in `files.collections`, which may be the
     * array form or an instance already.
     *
     * @param  FileCollection|array{accepts?: list<string>, max_size?: int|null, single?: bool, variants?: array<string, array{width?: int, height?: int, fit?: string}>, public?: bool}  $rules
     */
    public static function fromConfig(string $name, self|array $rules): self
    {
        if ($rules instanceof self) {
            return $rules;
        }

        return new self(
            name: $name,
            accepts: $rules['accepts'] ?? [],
            maxSize: $rules['max_size'] ?? null,
            single: $rules['single'] ?? false,
            variants: $rules['variants'] ?? [],
            public: (bool) ($rules['public'] ?? false),
        );
    }

    /**
     * An image collection with the two renditions almost every one of them
     * wants, so the common case is one call and not five arguments.
     */
    public static function images(string $name, ?int $maxSize = null, bool $single = false, bool $public = false): self
    {
        return new self(
            name: $name,
            accepts: ['image/*'],
            maxSize: $maxSize,
            single: $single,
            variants: [
                'thumb' => ['width' => 200, 'height' => 200, 'fit' => 'cover'],
                'preview' => ['width' => 1200, 'fit' => 'contain'],
            ],
            public: $public,
        );
    }

    /**
     * Does this collection take a file of this type?
     *
     * An empty list accepts anything, which is the right default for a
     * general-purpose attachment and the wrong one for an avatar -- hence the
     * named constructors above.
     */
    public function accepts(string $mimeType): bool
    {
        if ($this->accepts === []) {
            return true;
        }

        foreach ($this->accepts as $pattern) {
            if (str_ends_with($pattern, '/*')) {
                if (str_starts_with($mimeType, substr($pattern, 0, -1))) {
                    return true;
                }

                continue;
            }

            if ($pattern === $mimeType) {
                return true;
            }
        }

        return false;
    }

    public function allowsSize(int $bytes): bool
    {
        return $this->maxSize === null || $bytes <= $this->maxSize;
    }

    /**
     * The `accept` attribute for a file input. Advisory only -- the browser
     * filters the picker, the server decides.
     */
    public function acceptAttribute(): string
    {
        return implode(',', $this->accepts);
    }

    public function assertAccepts(string $mimeType, int $bytes): void
    {
        if (! $this->accepts($mimeType)) {
            throw new InvalidArgumentException(
                "Collection `{$this->name}` does not accept files of type `{$mimeType}`."
            );
        }

        if (! $this->allowsSize($bytes)) {
            throw new InvalidArgumentException(
                "Collection `{$this->name}` allows at most {$this->maxSize} bytes."
            );
        }
    }
}
