<div>
    <div class="">
        <div class="bg-white overflow-hidden rounded-xl shadow-soft">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-secondary-900">{{ __('base-tenant::accounts.management_title') }}</h2>
                    @if($isSystemAdmin)
                        <a href="{{ route('base-tenant.accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-hidden focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('base-tenant::accounts.add_new') }}
                        </a>
                    @endif
                </div>

                <div class="mb-4">
                    <input wire:model.live="search" type="text" placeholder="{{ __('base-tenant::accounts.search_placeholder') }}" class="w-full px-4 py-2 border border-secondary-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-secondary-200">
                        <thead class="bg-surface-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::accounts.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::accounts.users_count') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::accounts.status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::accounts.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-secondary-200">
                        @forelse($accounts as $account)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-secondary-900">
                                    {{ $account->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                        {{ $account->users_count }} {{ __('base-tenant::accounts.users') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                    @if($account->active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800">
                                            {{ __('base-tenant::accounts.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary-100 text-secondary-800">
                                            {{ __('base-tenant::accounts.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('base-tenant.accounts.edit', $account) }}" class="text-primary-600 hover:text-primary-900 mr-3">{{ __('base-tenant::accounts.edit') }}</a>
                                    <button wire:click="confirmDelete('{{ $account->id }}')" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-hidden focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">{{ __('base-tenant::accounts.delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-secondary-500">{{ __('base-tenant::accounts.no_accounts_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $accounts->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-account-modal" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('base-tenant::accounts.delete_account') }}</flux:heading>
            <flux:subheading>
                <p class="mt-4">
                    {{ __('base-tenant::accounts.delete_confirmation') }}
                </p>
            </flux:subheading>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('base-tenant::accounts.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button wire:click="deleteAccount" variant="danger">{{ __('base-tenant::accounts.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
