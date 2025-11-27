<?php

namespace Base\Tenant\Livewire;

use App\Models\Project;
use App\Models\User;
use Base\Tenant\Services\NotificationService;
use Base\Tenant\Notifications\Project\ProjectUserAddedNotification;
use Base\Tenant\Notifications\Project\ProjectUserRemovedNotification;
use Livewire\Component;

class ManageProjectMembers extends Component
{
    public Project $project;
    public $availableUsers = [];
    public $currentMembers = [];
    public $selectedUserId = null;

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->loadMembers();
    }

    public function loadMembers()
    {
        // Get all account users
        $accountUsers = $this->project->account->users;

        // Get ProjectAdmins (auto-assigned)
        $projectAdmins = $accountUsers->filter(function ($user) {
            return $user->isProjectAdminInAccount($this->project->account_id);
        });

        // Get assigned Collaborators
        $assignedCollaborators = $this->project->users;

        // Current members = ProjectAdmins + assigned Collaborators (deduplicated)
        $this->currentMembers = $projectAdmins->merge($assignedCollaborators)
            ->unique('id')
            ->map(function ($user) use ($projectAdmins) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'is_admin' => $projectAdmins->contains('id', $user->id),
                    'can_remove' => !$projectAdmins->contains('id', $user->id), // Can't remove admins
                ];
            })
            ->values()
            ->toArray();

        // Available users = Collaborators not yet assigned
        $assignedIds = collect($this->currentMembers)->pluck('id')->toArray();
        $this->availableUsers = $accountUsers
            ->filter(function ($user) use ($assignedIds) {
                return !in_array($user->id, $assignedIds)
                    && $user->isProjectTranslatorInAccount($this->project->account_id);
            })
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            })
            ->values()
            ->toArray();
    }

    public function addMember()
    {
        if (!$this->selectedUserId) {
            return;
        }

        $user = User::find($this->selectedUserId);

        if (!$user) {
            return;
        }

        // Add user to project
        $this->project->addUser($user);

        // Notify the added user
        NotificationService::notifySpecificUsers(
            [$user],
            new ProjectUserAddedNotification($this->project, auth()->user(), $user)
        );

        // Refresh lists
        $this->selectedUserId = null;
        $this->loadMembers();

        $this->dispatch('memberAdded');
    }

    public function removeMember($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        // Can't remove ProjectAdmins (they have account-level access)
        if ($user->isProjectAdminInAccount($this->project->account_id)) {
            return;
        }

        // Remove user from project
        $this->project->removeUser($user);

        // Notify the removed user
        NotificationService::notifySpecificUsers(
            [$user],
            new ProjectUserRemovedNotification($this->project, auth()->user(), $user)
        );

        // Refresh lists
        $this->loadMembers();

        $this->dispatch('memberRemoved');
    }

    public function render()
    {
        return view('base-tenant::livewire.manage-project-members');
    }
}
