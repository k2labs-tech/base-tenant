<div class="space-y-6">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <h2 class="text-2xl font-bold text-primary-900">Notifications</h2>
            <span class="px-3 py-1 text-sm font-medium bg-accent-100 text-accent-700 rounded-full">
                {{ auth()->user()->unreadNotifications()->count() }} unread
            </span>
        </div>

        @if(auth()->user()->unreadNotifications()->count() > 0)
            <button
                wire:click="markAllAsRead"
                class="px-4 py-2 text-sm font-medium text-white bg-accent-600 rounded-lg hover:bg-accent-700 transition-colors"
            >
                Mark All as Read
            </button>
        @endif
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-soft p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-primary-700 mb-2">Search</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search notifications..."
                    class="w-full px-4 py-2 border border-surface-300 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                >
            </div>

            <!-- Priority Filter -->
            <div>
                <label class="block text-sm font-medium text-primary-700 mb-2">Priority</label>
                <select
                    wire:model.live="filterPriority"
                    class="w-full px-4 py-2 border border-surface-300 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                >
                    <option value="all">All Priorities</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <!-- Read Status Filter -->
            <div>
                <label class="block text-sm font-medium text-primary-700 mb-2">Status</label>
                <select
                    wire:model.live="filterReadStatus"
                    class="w-full px-4 py-2 border border-surface-300 rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
                >
                    <option value="all">All</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if(count($selectedNotifications) > 0)
        <div class="bg-accent-50 border border-accent-200 rounded-lg p-4 flex items-center justify-between">
            <span class="text-sm font-medium text-accent-900">
                {{ count($selectedNotifications) }} notification(s) selected
            </span>
            <div class="flex items-center space-x-3">
                <button
                    wire:click="markSelectedAsRead"
                    class="px-3 py-1.5 text-sm font-medium text-accent-700 hover:text-accent-800"
                >
                    Mark as Read
                </button>
                <button
                    wire:click="deleteSelected"
                    wire:confirm="Are you sure you want to delete the selected notifications?"
                    class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700"
                >
                    Delete
                </button>
            </div>
        </div>
    @endif

    <!-- Notifications List -->
    <div class="bg-white rounded-xl shadow-soft overflow-hidden">
        @forelse($notifications as $notification)
            <div class="border-b border-surface-100 {{ $notification->read_at ? 'bg-surface-50' : 'bg-white' }}">
                <div class="p-6 flex items-start space-x-4">
                    <!-- Checkbox -->
                    <input
                        type="checkbox"
                        wire:model.live="selectedNotifications"
                        value="{{ $notification->id }}"
                        class="mt-1 rounded border-surface-300 text-accent-600 focus:ring-accent-500"
                    >

                    <!-- Priority Indicator -->
                    <div class="shrink-0 w-3 h-3 rounded-full mt-1
                        @if(($notification->data['priority'] ?? 'low') === 'high') bg-red-500
                        @elseif(($notification->data['priority'] ?? 'low') === 'medium') bg-yellow-500
                        @else bg-blue-500
                        @endif
                    "></div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-primary-900">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </h3>
                                <p class="mt-1 text-sm text-primary-600">
                                    {{ $notification->data['message'] ?? '' }}
                                </p>

                                @if(isset($notification->data['project']) && isset($notification->data['project']['name']))
                                    <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                        {{ $notification->data['project']['name'] }}
                                    </span>
                                @endif

                                <p class="mt-2 text-xs text-primary-500">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="shrink-0 flex items-center space-x-2">
                        @if(isset($notification->data['action_url']) && $notification->data['action_url'] && $notification->data['action_url'] !== '#')
                            <a
                                href="{{ $notification->data['action_url'] }}"
                                class="px-3 py-1.5 text-sm font-medium text-accent-600 hover:text-accent-700"
                            >
                                {{ $notification->data['action_text'] ?? 'View' }}
                            </a>
                        @endif

                        @if(!$notification->read_at)
                            <button
                                wire:click="markAsRead('{{ $notification->id }}')"
                                class="p-2 text-primary-400 hover:text-primary-600 rounded-lg hover:bg-surface-100"
                                title="Mark as read"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-primary-900">No notifications</h3>
                <p class="mt-2 text-sm text-primary-500">You don't have any notifications matching your filters.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
