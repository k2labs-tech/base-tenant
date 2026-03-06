<div x-data="{ open: @entangle('showDropdown') }" class="relative">
    <!-- Notification Bell Button -->
    <button
        @click="open = !open"
        wire:poll.{{ config('base-tenant.notifications.polling_interval', 30) }}s="refreshNotifications"
        class="relative p-2 rounded-lg hover:bg-surface-100 transition-colors"
    >
        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        @if($unreadCount > 0)
            <span class="absolute top-1 right-1 min-w-[1.25rem] h-5 px-1.5 flex items-center justify-center text-xs font-bold text-white bg-accent-500 rounded-full">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown -->
    <div
        x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed left-4 right-4 top-16 sm:absolute sm:left-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-96 bg-white rounded-xl shadow-lg border border-surface-200 z-50"
        style="display: none;"
    >
        <!-- Header -->
        <div class="px-4 py-3 border-b border-surface-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-primary-900">Notifications</h3>
            @if($unreadCount > 0)
                <button
                    wire:click="markAllAsRead"
                    class="text-xs text-accent-600 hover:text-accent-700 font-medium"
                >
                    Mark all read
                </button>
            @endif
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
            @forelse($recentNotifications as $notification)
                <div
                    class="px-4 py-3 border-b border-surface-100 hover:bg-surface-50 transition-colors {{ $notification['read_at'] ? 'opacity-60' : '' }}"
                >
                    <div class="flex items-start space-x-3">
                        <!-- Priority Indicator -->
                        <div class="shrink-0 w-2 h-2 rounded-full mt-2
                            @if($notification['priority'] === 'high') bg-red-500
                            @elseif($notification['priority'] === 'medium') bg-yellow-500
                            @else bg-blue-500
                            @endif
                        "></div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-primary-900">{{ $notification['title'] }}</p>
                            <p class="text-sm text-primary-600 mt-0.5">{{ $notification['message'] }}</p>
                            <p class="text-xs text-primary-500 mt-1">{{ $notification['time_ago'] }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="shrink-0 flex items-center space-x-2">
                            @if($notification['action_url'] !== '#')
                                <a
                                    href="{{ $notification['action_url'] }}"
                                    class="text-accent-600 hover:text-accent-700"
                                    @click="open = false"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @endif

                            @if(!$notification['read_at'])
                                <button
                                    wire:click="markAsRead('{{ $notification['id'] }}')"
                                    class="text-primary-400 hover:text-primary-600"
                                    title="Mark as read"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="mt-2 text-sm text-primary-500">No notifications yet</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-surface-200 text-center">
            <a
                href="{{ route('base-tenant.notifications.index') }}"
                class="text-sm text-accent-600 hover:text-accent-700 font-medium"
                @click="open = false"
            >
                View all notifications
            </a>
        </div>
    </div>
</div>
