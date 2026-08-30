<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Base\Tenant\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected UserInvite $invite,
        protected string $inviterName,
        protected string $accountName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url(route('base-tenant.invitations.accept', [
            'token' => $this->invite->token,
        ], false));

        return (new MailMessage)
            ->subject(__('base-tenant::invitations.email_subject', ['app' => config('app.name')]))
            ->greeting(__('base-tenant::invitations.email_greeting'))
            ->line(__('base-tenant::invitations.email_line1', [
                'inviter' => $this->inviterName,
                'account' => $this->accountName,
                'app' => config('app.name'),
            ]))
            ->action(__('base-tenant::invitations.email_action'), $acceptUrl)
            ->line(__('base-tenant::invitations.email_line2', [
                'days' => config('base-tenant.invitations.expires_in_days', 7),
            ]));
    }
}
