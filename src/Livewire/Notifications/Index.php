<?php

namespace Base\Tenant\Livewire\Notifications;

use Base\Tenant\Services\NotificationService;
use Base\Tenant\Support\Search;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $filterPriority = 'all';

    public $filterReadStatus = 'all'; // all, read, unread

    public $search = '';

    public $selectedNotifications = [];

    public bool $showDeleteModal = false;

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

    /**
     * Los tres filtros vuelven a su valor inicial de una vez.
     *
     * La página también: seguir en la cuarta después de soltar los filtros deja
     * una lista vacía encima de datos que sí existen.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'filterPriority', 'filterReadStatus']);
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

    public function confirmDeleteSelected()
    {
        $this->showDeleteModal = true;
    }

    public function deleteSelected()
    {
        $this->showDeleteModal = false;

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
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('data->title', Search::operator(), "%{$this->search}%")
                    ->orWhere('data->message', Search::operator(), "%{$this->search}%");
            });
        }

        $notifications = $query->paginate(config('base-tenant.notifications.per_page', 25));

        return view('base-tenant::livewire.notifications.index', [
            'notifications' => $notifications,
            // El recuento de la cabecera cuenta la bandeja entera, no la página
            // ni lo que dejen ver los filtros.
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'hasActiveFilters' => $this->search !== ''
                || $this->filterPriority !== 'all'
                || $this->filterReadStatus !== 'all',
        ])->layout('base-tenant::layouts.app');
    }
}
