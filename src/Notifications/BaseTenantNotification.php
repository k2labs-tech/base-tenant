<?php

namespace Base\Tenant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseTenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $title;
    protected string $message;
    protected string $actionUrl;
    protected string $actionText = 'View';
    protected string $icon = 'bell';
    protected string $priority = 'low'; // low, medium, high
    protected string $category;
    protected array $causer;
    protected array $project;
    protected array $details = [];

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText,
            'icon' => $this->icon,
            'priority' => $this->priority,
            'category' => $this->category,
            'causer' => $this->causer,
            'project' => $this->project,
            'details' => $this->details,
        ];
    }

    /**
     * Get notification priority color class
     */
    public function getPriorityColorClass(): string
    {
        return match($this->priority) {
            'high' => 'bg-red-500',
            'medium' => 'bg-yellow-500',
            'low' => 'bg-blue-500',
            default => 'bg-primary-500',
        };
    }
}
