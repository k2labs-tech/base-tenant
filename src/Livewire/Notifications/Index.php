<?php

namespace Base\Tenant\Livewire\Notifications;

use Base\Tenant\Services\NotificationService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $filterPriority = 'all';
    public $filterReadStatus = 'all'; // all, read, unread
    public $search = '';
    public $selectedNotifications = [];

    protected $queryString = [
        'filterPriority' => ['except' => 'all'],
        'filterReadStatus' => ['except' => 'all'],
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterPriority()
    {
        $this->resetPage();
    }

    public function updatingFilterReadStatus()
    {
        $this->resetPage();
    }

    public function markAsRead($notificationId)
    {
        NotificationService::markAsRead(auth()->user(), $notificationId);
        $this->dispatch('notificationRead');
    }

    public function markAllAsRead()
    {
        NotificationService::markAllAsRead(auth()->user());
        $this->selectedNotifications = [];
        $this->dispatch('notificationRead');
    }

    public function markSelectedAsRead()
    {
        foreach ($this->selectedNotifications as $notificationId) {
            NotificationService::markAsRead(auth()->user(), $notificationId);
        }

        $this->selectedNotifications = [];
        $this->dispatch('notificationRead');
    }

    public function deleteSelected()
    {
        auth()->user()->notifications()
            ->whereIn('id', $this->selectedNotifications)
            ->delete();

        $this->selectedNotifications = [];
    }

    public function render()
    {
        $query = auth()->user()->notifications();

        // Apply priority filter
        if ($this->filterPriority !== 'all') {
            $query->where('data->priority', $this->filterPriority);
        }

        // Apply read status filter
        if ($this->filterReadStatus === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($this->filterReadStatus === 'unread') {
            $query->whereNull('read_at');
        }

        // Apply search
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('data->title', 'like', "%{$this->search}%")
                  ->orWhere('data->message', 'like', "%{$this->search}%");
            });
        }

        $notifications = $query->paginate(config('base-tenant.notifications.per_page', 25));

        return view('base-tenant::livewire.notifications.index', [
            'notifications' => $notifications,
        ])->layout('base-tenant::layouts.app');
    }
}
