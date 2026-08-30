<?php

namespace Base\Tenant\Http\Controllers;

use Base\Tenant\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $id)
    {
        NotificationService::markAsRead(auth()->user(), $id);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        NotificationService::markAllAsRead(auth()->user());

        return response()->json(['success' => true]);
    }
}
