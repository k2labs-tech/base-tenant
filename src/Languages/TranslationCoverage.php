<?php

declare(strict_types=1);

namespace Base\Tenant\Languages;

use Illuminate\Support\Facades\File;

/**
 * How much of the reference locale a given locale actually has.
 *
 * Enabling an incomplete language is a legitimate thing to do -- the fallback
 * covers the holes -- but it should be a decision made with the number in
 * front of you rather than a surprise a customer reports.
 */
class TranslationCoverage
{
    /**
     * @param  list<string>  $paths  directories holding `{locale}/*.php` files
     */
    public function __construct(protected array $paths = []) {}

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        if ($this->paths !== []) {
            return $this->paths;
        }

        // The package's own files, plus the application's. A host that has
        // published or written its own translations is measured on both.
        return array_values(array_filter([
            dirname(__DIR__, 2).'/resources/lang',
            function_exists('lang_path') ? lang_path() : null,
        ], fn (?string $path): bool => $path !== null && File::isDirectory($path)));
    }

    /**
     * @return array{total: int, translated: int, missing: list<string>, percentage: int}
     */
    public function for(string $locale, ?string $reference = null): array
    {
        $reference ??= (string) config('base-tenant.languages.reference', 'en');

        $expected = $this->keysFor($reference);
        $actual = $this->keysFor($locale);

        $missing = array_values(array_diff($expected, $actual));
        $total = count($expected);

        return [
            'total' => $total,
            'translated' => $total - count($missing),
            'missing' => $missing,
            // A locale with no reference to compare against is complete by
            // definition rather than zero, which would read as broken.
            'percentage' => $total === 0 ? 100 : (int) floor(($total - count($missing)) / $total * 100),
        ];
    }

    /**
     * Every dotted key present for a locale, across every source directory.
     *
     * @return list<string>
     */
    public function keysFor(string $locale): array
    {
        $keys = [];

        foreach ($this->paths() as $path) {
            $directory = $path.'/'.$locale;

            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = require $file->getPathname();

                if (! is_array($contents)) {
                    continue;
                }

                foreach ($this->flatten($contents, $file->getFilenameWithoutExtension()) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    protected function flatten(array $values, string $prefix): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $full = $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = [...$keys, ...$this->flatten($value, $full)];

                continue;
            }

            // A key present but empty is not translated. Counting it would let
            // a locale reach 100% while showing blanks.
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $keys[] = $full;
        }

        return $keys;
    }
}
