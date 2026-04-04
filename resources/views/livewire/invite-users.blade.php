<div>
    <div class="bg-white overflow-hidden rounded-xl shadow-soft mb-6">
        <div class="p-6">
            <h2 class="text-2xl font-semibold text-secondary-900 mb-4">{{ __('base-tenant::invites.title') }}</h2>
            <p class="text-sm text-secondary-600 mb-4">{{ __('base-tenant::invites.description') }}</p>

            <form wire:submit="sendInvitations">
                <div class="mb-4">
                    <x-base-tenant::input-label for="emails" :value="__('base-tenant::invites.emails_label')" />
                    <textarea
                        wire:model="emails"
                        id="emails"
                        rows="4"
                        class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 placeholder-secondary-400 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200"
                        placeholder="{{ __('base-tenant::invites.emails_placeholder') }}"
                        required
                    ></textarea>
                    <p class="mt-1 text-xs text-secondary-500">{{ __('base-tenant::invites.emails_help') }}</p>
                    <x-base-tenant::input-error :messages="$errors->get('emails')" class="mt-2" />
                </div>

                <x-base-tenant::primary-button>
                    {{ __('base-tenant::invites.send_button') }}
                </x-base-tenant::primary-button>
            </form>
        </div>
    </div>

    <!-- Invitations List -->
    <div class="bg-white overflow-hidden rounded-xl shadow-soft">
        <div class="p-6">
            <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('base-tenant::invites.list_title') }}</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-secondary-200">
                    <thead class="bg-surface-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::invites.table.email') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::invites.table.status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::invites.table.sent_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::invites.table.used_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-secondary-200">
                        @forelse($invitations as $invitation)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-secondary-900">
                                    {{ $invitation->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($invitation->isUsed())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ __('base-tenant::invites.status.used') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ __('base-tenant::invites.status.pending') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                    {{ $invitation->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                    {{ $invitation->used_at?->format('d/m/Y H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-secondary-500">
                                    {{ __('base-tenant::invites.no_invitations') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invitations->links() }}
            </div>
        </div>
    </div>
</div>
