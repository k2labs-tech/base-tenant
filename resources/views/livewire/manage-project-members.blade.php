<div class="space-y-6">
    {{-- Add Member Section --}}
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Add Collaborator</h3>

        @if(count($availableUsers) > 0)
            <div class="flex gap-3">
                <select wire:model="selectedUserId" class="flex-1 rounded-lg border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Select a collaborator...</option>
                    @foreach($availableUsers as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }} ({{ $user['email'] }})</option>
                    @endforeach
                </select>

                <button
                    wire:click="addMember"
                    :disabled="!$wire.selectedUserId"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Add
                </button>
            </div>
        @else
            <p class="text-sm text-zinc-600 dark:text-zinc-400">All collaborators are already assigned to this project.</p>
        @endif
    </div>

    {{-- Current Members List --}}
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
            Project Members ({{ count($currentMembers) }})
        </h3>

        <div class="space-y-2">
            @foreach($currentMembers as $member)
                <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        {{-- Avatar --}}
                        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold">
                            {{ $member['initials'] }}
                        </div>

                        {{-- User Info --}}
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">
                                {{ $member['name'] }}
                                @if($member['is_admin'])
                                    <span class="ml-2 text-xs px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded">
                                        Project Admin
                                    </span>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $member['email'] }}</div>
                        </div>
                    </div>

                    {{-- Remove Button (only for Collaborators) --}}
                    @if($member['can_remove'])
                        <button
                            wire:click="removeMember('{{ $member['id'] }}')"
                            wire:confirm="Remove {{ $member['name'] }} from this project?"
                            class="px-3 py-1 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        >
                            Remove
                        </button>
                    @else
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Auto-assigned</span>
                    @endif
                </div>
            @endforeach

            @if(count($currentMembers) === 0)
                <p class="text-sm text-zinc-600 dark:text-zinc-400 text-center py-4">
                    No members assigned to this project yet.
                </p>
            @endif
        </div>
    </div>
</div>
