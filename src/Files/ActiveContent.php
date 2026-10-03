<?php

declare(strict_types=1);

namespace Base\Tenant\Files;

/**
 * File types a browser will run rather than merely display.
 *
 * An SVG is an image to the uploader and a document to the browser: opened
 * on its own it executes the `<script>` it carries, in the origin it was served
 * from. The same goes for HTML, XHTML and XML with a stylesheet. Served from the
 * application's own domain, any of them is a stored XSS waiting for a link.
 *
 * Two consequences, applied wherever the package serves bytes:
 *
 *   - a wildcard such as `image/*` or `text/*` never matches these types; a
 *     collection that wants them names them explicitly
 *   - when they are served, the response carries a sandboxing CSP, so even an
 *     opened file runs nothing and loads nothing
 */
final class ActiveContent
{
    /**
     * Enough for an SVG to render with its inline styles; no scripts, no
     * requests, no forms, and an opaque origin that cannot read the session.
     */
    public const POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    /**
     * Types rendered as documents that can run script. Anything ending in
     * `+xml` is treated the same way.
     */
    public const DOCUMENT_TYPES = [
        'image/svg+xml',
        'text/html',
        'application/xhtml+xml',
        'text/xml',
        'application/xml',
        'text/xsl',
    ];

    /**
     * Types a `<script src>` on the application's own pages would execute.
     * Served as plain text instead, so `nosniff` stops the browser running
     * them even where the page's CSP trusts `'self'`.
     */
    public const SCRIPT_TYPES = [
        'text/javascript',
        'application/javascript',
        'application/x-javascript',
        'application/ecmascript',
        'text/ecmascript',
    ];

    public static function is(string $mimeType): bool
    {
        $mimeType = self::normalise($mimeType);

        return in_array($mimeType, self::DOCUMENT_TYPES, true)
            || in_array($mimeType, self::SCRIPT_TYPES, true)
            || str_ends_with($mimeType, '+xml');
    }

    /**
     * The headers to serve a file of this type with.
     *
     * `nosniff` always, so the browser takes the declared type at its word.
     * For active content, the sandboxing policy too, and scripts are relabelled
     * as plain text.
     *
     * @return array<string, string>
     */
    public static function headers(string $mimeType): array
    {
        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (! self::is($mimeType)) {
            return $headers;
        }

        if (in_array(self::normalise($mimeType), self::SCRIPT_TYPES, true)) {
            $headers['Content-Type'] = 'text/plain; charset=utf-8';
        }

        $headers['Content-Security-Policy'] = self::POLICY;

        return $headers;
    }

    private static function normalise(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType, 2)[0]));
    }
}
