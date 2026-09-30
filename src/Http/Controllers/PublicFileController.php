<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Models\File;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The stable address of a file in a public collection.
 *
 * No session and no account in context: this is what a status page embeds.
 * What keeps it from being a way into private files is the check below, made
 * on every request against the collection's current rules -- a file whose
 * collection is not public answers exactly as a file that does not exist.
 */
class PublicFileController extends Controller
{
    /**
     * An hour in shared caches: long enough to spare the application a logo
     * request per visitor, short enough that making a collection private
     * again takes effect the same afternoon.
     */
    public const CACHE_SECONDS = 3600;

    public function __invoke(Request $request, string $file): StreamedResponse
    {
        $model = File::query()->acrossAccounts()->find($file);

        abort_unless($model && $model->isPublic(), 404);

        $path = $model->path;

        if ($variant = $request->query('variant')) {
            $path = is_string($variant) ? ($model->variants[$variant] ?? null) : null;

            abort_if($path === null, 404);
        }

        abort_unless($model->storage()->exists($path), 404);

        return $model->storage()->response($path, $model->name, [
            'Content-Type' => $model->mime_type,
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
