<div>
    <div class="">
        <div class="bg-white overflow-hidden rounded-xl shadow-soft">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-secondary-900">{{ __('base-tenant::users.management_title') }}</h2>
                    <a href="{{ route('base-tenant.users.create') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-hidden focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('base-tenant::users.add_new') }}
                    </a>
                </div>


                <div class="mb-4">
                    <input wire:model.live="search" type="text" placeholder="{{ __('base-tenant::users.search_placeholder') }}" class="w-full px-4 py-2 border border-secondary-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-secondary-200">
                        <thead class="bg-surface-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::users.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::users.email') }}</th>
                            @if($isSystemAdmin)
                                <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::users.accounts') }}</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::users.roles') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-secondary-500 uppercase tracking-wider">{{ __('base-tenant::users.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-secondary-200">
                        @forelse($users as $user)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-secondary-900">
                                    {{ $user->name }}
                                    @if($user->id === Auth::id())
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800">
                                                    {{ __('base-tenant::users.you') }}
                                                </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">{{ $user->email }}</td>
                                @if($isSystemAdmin)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                        @if($user->accounts->count() > 0)
                                            @foreach($user->accounts as $account)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary-100 text-secondary-800 mr-1">
                                                            {{ $account->name ?? $account->id }}
                                                        </span>
                                            @endforeach
                                        @elseif($user->is_admin || is_null($user->account_id))
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800">
                                                        {{ __('base-tenant::users.system_admin') }}
                                                    </span>
                                        @else
                                            <span class="text-secondary-400">{{ __('base-tenant::users.no_accounts') }}</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 mr-1">
                                                    {{ $role->name }}
                                                </span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('base-tenant.users.edit', $user) }}" class="text-primary-600 hover:text-primary-900 mr-3">{{ __('base-tenant::users.edit') }}</a>
                                    @if($user->id !== Auth::id())
                                        <button wire:click="confirmDelete('{{ $user->id }}')" class="text-error-600 hover:text-error-900">{{ __('base-tenant::users.remove') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isSystemAdmin ? 5 : 4 }}" class="px-6 py-4 text-center text-secondary-500">{{ __('base-tenant::users.no_users_found') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>



    <!-- Delete Confirmation Modal -->
    <x-confirmation-modal wire:model="showDeleteModal">
        <x-slot name="title">
            {{ __('base-tenant::users.remove_user') }}
        </x-slot>

        <x-slot name="content">
            {{ __('base-tenant::users.remove_confirmation') }}
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$set('showDeleteModal', false)" wire:loading.attr="disabled">
                {{ __('base-tenant::app.actions.cancel') }}
            </x-secondary-button>

            <x-danger-button class="ml-3" wire:click="deleteUser" wire:loading.attr="disabled">
                {{ __('base-tenant::users.remove_user') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
