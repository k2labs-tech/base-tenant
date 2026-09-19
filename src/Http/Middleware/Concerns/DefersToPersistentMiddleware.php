<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * For middleware that Livewire re-runs on every component update.
 *
 * Every action in this product's screens is a POST to Livewire's update
 * endpoint, so a rule applied only to page routes would be skipped by the very
 * requests that do the work. The package registers these middleware as
 * Livewire persistent middleware: on an update request Livewire rebuilds the
 * original page request -- same session, same headers, the page's own route --
 * and runs them against it.
 *
 * That makes the update request itself the wrong place to run them, twice
 * over. Its route is `livewire.update`, so an escape hatch written as
 * `routeIs('base-tenant.profile')` could never match and would lock a user
 * out of the one screen where they can comply; and running the rule there
 * as well as on the rebuilt request would apply it twice.
 */
trait DefersToPersistentMiddleware
{
    /**
     * Is this Livewire's own update request, and will the replay run this
     * middleware for it?
     *
     * The replay gathers the page route's middleware -- its own and the groups
     * it belongs to -- never the kernel's global stack. So the request is only
     * deferred when this class reached the update route through a group,
     * which means it also sits on the page routes the replay reads. Attached
     * globally, it is not in either list, and the only place it can run is
     * right here.
     */
    protected function isLivewireUpdateRequest(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        $name = $route->getName();

        if (! is_string($name) || ! str_ends_with($name, 'livewire.update')) {
            return false;
        }

        foreach (app(Router::class)->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && Str::before($middleware, ':') === static::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this a `wire:poll` tick rather than something the person did?
     *
     * A poll is an update whose components carry no property changes and
     * call nothing but `$refresh`. A tab left open polls for hours, and a
     * rule that counted each tick as activity would never see anyone idle.
     */
    protected function isLivewirePoll(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire')) {
            return false;
        }

        $components = $request->json('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! empty($component['updates'] ?? [])) {
                return false;
            }

            foreach ($component['calls'] ?? [] as $call) {
                if (($call['method'] ?? null) !== '$refresh') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Refuse the request with a JSON body, in the one way that survives the
     * replay.
     *
     * Thrown, not returned: Livewire's persistent-middleware replay keeps a
     * redirect and discards every other response, so a middleware that
     * `return`ed a 403 would let the action run for anybody who sends
     * `Accept: application/json`.
     */
    protected function refuseWithJson(string $message, int $status): never
    {
        abort(response()->json(['message' => $message], $status));
    }
}
