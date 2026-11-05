<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Facades\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * The badges of the menu the person sees, for a page that refreshes them
 * without reloading (`menu-badges-refresh`): finishing a task on a screen
 * that stays where it is should still lower the count beside "Tasks".
 */
class MenuBadgeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Menu::badges());
    }
}
