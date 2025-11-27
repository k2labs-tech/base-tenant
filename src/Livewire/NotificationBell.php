<?php

namespace Base\Tenant\Livewire;

use Base\Tenant\Services\NotificationService;
use Livewire\Component;

class NotificationBell extends Component
{
    public $unreadCount = 0;
    public $recentNotifications = [];
    public $showDropdown = false;

    protected $listeners = ['notificationRead' => 'refreshNotifications'];

    public function mount()
    {
        $this->refreshNotifications();
    }

    public function refreshNotifications()
    {
        $user = auth()->user();

        $this->unreadCount = NotificationService::getUnreadCount($user);

        $this->recentNotifications = $user->notifications()
            ->take(config('base-tenant.notifications.dropdown_limit', 10))
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? '#',
                    'priority' => $notification->data['priority'] ?? 'low',
                    'icon' => $notification->data['icon'] ?? 'bell',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'time_ago' => $notification->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    public function markAsRead($notificationId)
    {
        NotificationService::markAsRead(auth()->user(), $notificationId);
        $this->refreshNotifications();
        $this->dispatch('notificationRead');
    }

    public function markAllAsRead()
    {
        NotificationService::markAllAsRead(auth()->user());
        $this->refreshNotifications();
        $this->dispatch('notificationRead');
    }

    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
    }

    public function render()
    {
        return view('base-tenant::livewire.notification-bell');
    }
}
