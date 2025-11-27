<?php

namespace Base\Tenant\Services;

use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Notify all project users except the causer
     *
     * @param mixed $project Project model instance
     * @param \Illuminate\Notifications\Notification $notification Notification instance
     * @param mixed $causer User who triggered the action
     * @return void
     */
    public static function notifyProjectUsers($project, $notification, $causer): void
    {
        // Get all users of the project except the one who triggered the action
        $recipients = $project->users()
            ->where('users.id', '!=', $causer->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        // Send notification to all recipients
        Notification::send($recipients, $notification);
    }

    /**
     * Get unread notification count for a user
     *
     * @param mixed $user User model instance
     * @return int
     */
    public static function getUnreadCount($user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark notification as read
     *
     * @param mixed $user User model instance
     * @param string $notificationId Notification UUID
     * @return void
     */
    public static function markAsRead($user, string $notificationId): void
    {
        $notification = $user->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all notifications as read
     *
     * @param mixed $user User model instance
     * @return void
     */
    public static function markAllAsRead($user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
