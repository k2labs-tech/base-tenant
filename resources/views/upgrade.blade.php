<x-base-tenant::app-layout>
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-8 max-w-md w-full text-center">
            <div class="mx-auto w-16 h-16 bg-accent-100 dark:bg-accent-900/40 rounded-full flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>

            <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white mb-3">
                {{ __('base-tenant::plans.upgrade_title') }}
            </h2>

            <p class="text-zinc-600 dark:text-zinc-300 mb-8">
                {{ __('base-tenant::plans.upgrade_message') }}
            </p>

            <a href="{{ route('base-tenant.billing') }}" class="inline-flex items-center justify-center px-6 py-3 bg-accent-600 text-white font-medium rounded-lg hover:bg-accent-700 transition-colors">
                {{ __('base-tenant::plans.upgrade_action') }}
            </a>
        </div>
    </div>
</x-base-tenant::app-layout>
