<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Models\UserInvite;
use Base\Tenant\Notifications\InviteNotification;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('base-tenant::layouts.app')]
class InviteUsers extends Component
{
    use WithPagination;

    public string $emails = '';

    public function mount(): void
    {
        $user = auth()->user();

        // Only account owners can invite — super-admins have no account context
        if ($user->is_admin || ! $user->hasRole(config('base-tenant.registration.default_role'))) {
            abort(403);
        }
    }

    public function sendInvitations(): void
    {
        $this->validate([
            'emails' => 'required|string',
        ]);

        $accountId = session('current_account_id');
        if (! $accountId && ! auth()->user()->is_admin) {
            Flux::toast(variant: 'danger', heading: __('base-tenant::invites.error'), text: __('base-tenant::invites.no_account'));

            return;
        }

        // Parse emails: split by comma, newline, semicolon
        $emailList = preg_split('/[\s,;]+/', $this->emails);
        $emailList = array_filter(array_map('trim', $emailList));
        $emailList = array_unique($emailList);

        $sent = 0;
        $skipped = 0;
        $invalid = 0;

        $userModel = config('base-tenant.models.user', \Base\Tenant\Models\User::class);

        foreach ($emailList as $email) {
            $email = strtolower($email);

            // Validate email format
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;

                continue;
            }

            // Skip if user already exists
            if ($userModel::where('email', $email)->exists()) {
                $skipped++;

                continue;
            }

            // Skip if pending invitation already exists for this account
            if (UserInvite::where('email', $email)->where('account_id', $accountId)->pending()->exists()) {
                $skipped++;

                continue;
            }

            // Create invitation
            $invite = UserInvite::create([
                'email' => $email,
                'account_id' => $accountId,
                'role' => config('base-tenant.registration.invite_default_role'),
                'token' => UserInvite::generateToken(),
            ]);

            // Send notification
            Notification::route('mail', $email)->notify(new InviteNotification($invite));

            $sent++;
        }

        $this->emails = '';

        $message = __('base-tenant::invites.results', ['sent' => $sent, 'skipped' => $skipped]);
        if ($invalid > 0) {
            $message .= ' '.__('base-tenant::invites.invalid_emails', ['count' => $invalid]);
        }

        Flux::toast(variant: 'success', heading: __('base-tenant::invites.invitations_sent'), text: $message);
    }

    public function render()
    {
        $accountId = session('current_account_id');

        $invitations = UserInvite::query()
            ->when($accountId, fn ($q) => $q->forAccount($accountId))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('base-tenant::livewire.invite-users', [
            'invitations' => $invitations,
        ]);
    }
}
