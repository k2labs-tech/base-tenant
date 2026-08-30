<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Languages\LangSyncerClient;
use Base\Tenant\Support\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * LangSyncer telling us a language is finished.
 *
 * This is the piece that makes a new language appear without a release: the
 * translation is completed upstream, the webhook fires, the files are written
 * and the caches are dropped.
 */
class LangSyncerWebhookController extends Controller
{
    public function __invoke(Request $request, LangSyncerClient $client): JsonResponse
    {
        abort_unless(Module::enabled(Module::LANGUAGES), 404);

        // An unsigned webhook endpoint is an unauthenticated command runner.
        // The signature is checked against the raw body, before any parsing,
        // because parsing and re-encoding does not always round trip.
        if (! $client->verifySignature($request->getContent(), $request->header('X-LangSyncer-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $locales = array_filter((array) $request->input('locales', []));

        Artisan::queue('k2labs-base:lang-pull', [
            'locale' => $locales,
        ]);

        // 202, not 200: the work is queued. Telling the sender it is done
        // would invite them to stop retrying before anything has happened.
        return response()->json(['message' => 'Queued.'], 202);
    }
}
