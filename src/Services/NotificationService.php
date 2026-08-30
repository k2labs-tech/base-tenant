<?php

namespace Base\Tenant\Services;

use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Notify all users with access to a project (excluding the causer)
     * Uses $project->getAllMembers() which includes:
     * - ProjectAdmins (account-level access)
     * - Collaborators explicitly assigned to the project
     *
     * @param  mixed  $project  Project model instance
     * @param  \Illuminate\Notifications\Notification  $notification  Notification instance
     * @param  mixed  $causer  User who triggered the action
     */
    public static function notifyProjectUsers($project, $notification, $causer): void
    {
        $recipients = $project->getAllMembers()
            ->where('id', '!=', $causer->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    /**
     * Notify specific users
     * Used when you already have the exact list of recipients
     *
     * @param  array  $users  Array of User model instances
     * @param  \Illuminate\Notifications\Notification  $notification  Notification instance
     */
    public static function notifySpecificUsers(array $users, $notification): void
    {
        if (empty($users)) {
            return;
        }

        Notification::send($users, $notification);
    }

    /**
     * Get unread notification count for a user
     *
     * @param  mixed  $user  User model instance
     */
    public static function getUnreadCount($user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark notification as read
     *
     * @param  mixed  $user  User model instance
     * @param  string  $notificationId  Notification UUID
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
     * @param  mixed  $user  User model instance
     */
    public static function markAllAsRead($user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
