<x-base-tenant::app-layout>
    <x-slot name="header">
        {{ __('base-tenant::app.dashboard.title') }}
    </x-slot>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Stat Card 1 -->
        <div class="bg-white rounded-xl p-6 shadow-soft hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600">{{ __('base-tenant::app.dashboard.stats.total_revenue') }}</p>
                    <p class="text-2xl font-semibold text-primary-900 mt-1">$12,345</p>
                    <p class="text-xs text-success mt-2 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ __('base-tenant::app.dashboard.stats.from_last_month', ['percent' => 12]) }}
                    </p>
                </div>
                <div class="p-3 bg-accent-100 rounded-lg">
                    <svg class="w-6 h-6 text-accent-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Stat Card 2 -->
        <div class="bg-white rounded-xl p-6 shadow-soft hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600">{{ __('base-tenant::app.dashboard.stats.active_users') }}</p>
                    <p class="text-2xl font-semibold text-primary-900 mt-1">2,543</p>
                    <p class="text-xs text-success mt-2 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ __('base-tenant::app.dashboard.stats.from_last_week', ['percent' => 8]) }}
                    </p>
                </div>
                <div class="p-3 bg-info/10 rounded-lg">
                    <svg class="w-6 h-6 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-white rounded-xl p-6 shadow-soft hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600">{{ __('base-tenant::app.dashboard.stats.conversion_rate') }}</p>
                    <p class="text-2xl font-semibold text-primary-900 mt-1">24.7%</p>
                    <p class="text-xs text-error mt-2 flex items-center">
                        <svg class="w-3 h-3 mr-1 rotate-180" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ __('base-tenant::app.dashboard.stats.from_last_week', ['percent' => -3]) }}
                    </p>
                </div>
                <div class="p-3 bg-success/10 rounded-lg">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Stat Card 4 -->
        <div class="bg-white rounded-xl p-6 shadow-soft hover:shadow-lg transition-shadow duration-300">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600">{{ __('base-tenant::app.dashboard.stats.avg_session') }}</p>
                    <p class="text-2xl font-semibold text-primary-900 mt-1">4m 23s</p>
                    <p class="text-xs text-primary-500 mt-2">{{ __('base-tenant::app.dashboard.stats.no_change') }}</p>
                </div>
                <div class="p-3 bg-warning/10 rounded-lg">
                    <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Chart Card -->
            <div class="bg-white rounded-xl shadow-soft p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-primary-900">{{ __('base-tenant::app.dashboard.revenue_overview') }}</h3>
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-surface-100 rounded-lg hover:bg-surface-200 transition-colors">
                            {{ __('base-tenant::app.dashboard.periods.week') }}
                        </button>
                        <button class="px-3 py-1.5 text-xs font-medium text-white bg-accent-600 rounded-lg hover:bg-accent-700 transition-colors">
                            {{ __('base-tenant::app.dashboard.periods.month') }}
                        </button>
                        <button class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-surface-100 rounded-lg hover:bg-surface-200 transition-colors">
                            {{ __('base-tenant::app.dashboard.periods.year') }}
                        </button>
                    </div>
                </div>
                <!-- Placeholder for chart -->
                <div class="h-64 bg-surface-50 rounded-lg flex items-center justify-center">
                    <span class="text-primary-400">{{ __('base-tenant::app.dashboard.chart_placeholder') }}</span>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-soft">
                <div class="px-6 py-4 border-b border-surface-200">
                    <h3 class="text-lg font-semibold text-primary-900">{{ __('base-tenant::app.dashboard.recent_activity') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Activity Item -->
                    <div class="flex items-start space-x-3">
                        <div class="shrink-0 w-2 h-2 bg-accent-500 rounded-full mt-2"></div>
                        <div class="flex-1">
                            <p class="text-sm text-primary-900">
                                <span class="font-medium">Sarah Chen</span> completed the onboarding process
                            </p>
                            <p class="text-xs text-primary-500 mt-1">2 hours ago</p>
                        </div>
                    </div>
                    <!-- Activity Item -->
                    <div class="flex items-start space-x-3">
                        <div class="shrink-0 w-2 h-2 bg-success rounded-full mt-2"></div>
                        <div class="flex-1">
                            <p class="text-sm text-primary-900">
                                <span class="font-medium">New subscription</span> from Tech Corp
                            </p>
                            <p class="text-xs text-primary-500 mt-1">4 hours ago</p>
                        </div>
                    </div>
                    <!-- Activity Item -->
                    <div class="flex items-start space-x-3">
                        <div class="shrink-0 w-2 h-2 bg-info rounded-full mt-2"></div>
                        <div class="flex-1">
                            <p class="text-sm text-primary-900">
                                <span class="font-medium">System update</span> completed successfully
                            </p>
                            <p class="text-xs text-primary-500 mt-1">Yesterday at 11:00 PM</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Content -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-soft p-6">
                <h3 class="text-lg font-semibold text-primary-900 mb-4">{{ __('base-tenant::app.dashboard.quick_actions') }}</h3>
                <div class="space-y-3">
                    <button class="w-full px-4 py-2.5 bg-accent-600 text-white rounded-lg hover:bg-accent-700 transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>{{ __('base-tenant::app.dashboard.create_new_project') }}</span>
                    </button>
                    <button class="w-full px-4 py-2.5 bg-surface-100 text-primary-700 rounded-lg hover:bg-surface-200 transition-colors">
                        {{ __('base-tenant::app.dashboard.invite_team_member') }}
                    </button>
                    <button class="w-full px-4 py-2.5 bg-surface-100 text-primary-700 rounded-lg hover:bg-surface-200 transition-colors">
                        {{ __('base-tenant::app.dashboard.generate_report') }}
                    </button>
                </div>
            </div>

            <!-- Team Members -->
            <div class="bg-white rounded-xl shadow-soft p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-primary-900">{{ __('base-tenant::app.dashboard.team_members') }}</h3>
                    <a href="#" class="text-sm text-accent-600 hover:text-accent-700">{{ __('base-tenant::app.dashboard.view_all') }}</a>
                </div>
                <div class="space-y-3">
                    <!-- Team Member -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-accent-100 rounded-full flex items-center justify-center">
                            <span class="text-accent-600 font-medium text-sm">JD</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-primary-900">John Doe</p>
                            <p class="text-xs text-primary-500">{{ __('base-tenant::app.dashboard.roles.admin') }}</p>
                        </div>
                        <span class="w-2 h-2 bg-success rounded-full"></span>
                    </div>
                    <!-- Team Member -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-info/10 rounded-full flex items-center justify-center">
                            <span class="text-info font-medium text-sm">SC</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-primary-900">Sarah Chen</p>
                            <p class="text-xs text-primary-500">{{ __('base-tenant::app.dashboard.roles.developer') }}</p>
                        </div>
                        <span class="w-2 h-2 bg-success rounded-full"></span>
                    </div>
                    <!-- Team Member -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-warning/10 rounded-full flex items-center justify-center">
                            <span class="text-warning font-medium text-sm">MJ</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-primary-900">Mike Johnson</p>
                            <p class="text-xs text-primary-500">{{ __('base-tenant::app.dashboard.roles.designer') }}</p>
                        </div>
                        <span class="w-2 h-2 bg-primary-300 rounded-full"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-base-tenant::app-layout>
