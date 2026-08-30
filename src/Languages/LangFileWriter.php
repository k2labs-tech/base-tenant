<?php

declare(strict_types=1);

namespace Base\Tenant\Languages;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Turn a flat map of dotted keys into the `lang/{locale}/*.php` files Laravel
 * reads, and read them back the same way.
 *
 * Writing is a merge, not a replacement. A locale that comes back from
 * translation with two thirds of its keys must not delete the third the
 * project wrote by hand.
 */
class LangFileWriter
{
    public function __construct(protected string $root) {}

    public static function forApplication(): self
    {
        return new self(function_exists('lang_path') ? lang_path() : base_path('lang'));
    }

    /**
     * Every key of a locale, flattened to `file.nested.key`.
     *
     * @return array<string, string>
     */
    public function read(string $locale): array
    {
        $directory = $this->root.'/'.$locale;

        if (! File::isDirectory($directory)) {
            return [];
        }

        $keys = [];

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = require $file->getPathname();

            if (! is_array($contents)) {
                continue;
            }

            foreach (Arr::dot($contents) as $key => $value) {
                if (is_string($value)) {
                    $keys[$file->getFilenameWithoutExtension().'.'.$key] = $value;
                }
            }
        }

        return $keys;
    }

    /**
     * Merge translations into a locale and return how many keys changed.
     *
     * @param  array<string, string>  $translations
     */
    public function write(string $locale, array $translations): int
    {
        $byFile = [];

        foreach ($translations as $key => $value) {
            if (! str_contains($key, '.') || ! is_string($value) || trim($value) === '') {
                continue;
            }

            [$file, $rest] = explode('.', $key, 2);

            $byFile[$file][$rest] = $value;
        }

        $directory = $this->root.'/'.$locale;

        File::ensureDirectoryExists($directory);

        $changed = 0;

        foreach ($byFile as $file => $entries) {
            $path = $directory.'/'.$file.'.php';

            $existing = File::exists($path) ? (require $path) : [];
            $existing = is_array($existing) ? $existing : [];

            $merged = $existing;

            foreach ($entries as $key => $value) {
                if (Arr::get($merged, $key) !== $value) {
                    $changed++;
                }

                Arr::set($merged, $key, $value);
            }

            File::put($path, $this->render($merged));
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function render(array $values, int $depth = 1): string
    {
        if ($depth === 1) {
            return "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n\n".$this->body($values, 1)."\n];\n";
        }

        return $this->body($values, $depth);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function body(array $values, int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $lines = '';

        foreach ($values as $key => $value) {
            $lines .= $indent."'".$this->escape((string) $key)."' => ";

            $lines .= is_array($value)
                ? "[\n".$this->body($value, $depth + 1).$indent."],\n"
                : "'".$this->escape((string) $value)."',\n";
        }

        return $lines;
    }

    /**
     * Single-quoted strings escape only the quote and the backslash. Escaping
     * more would put a literal backslash in front of characters that never
     * needed one, and translators write apostrophes constantly.
     */
    protected function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
