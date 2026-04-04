<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Base\Tenant\Models\UserInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public UserInvite $invite,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accountName = $this->invite->account?->name ?? config('app.name');

        return (new MailMessage)
            ->subject(__('base-tenant::invites.email_subject', ['account' => $accountName]))
            ->line(__('base-tenant::invites.email_line1', ['account' => $accountName]))
            ->action(__('base-tenant::invites.email_action'), $this->invite->getRegisterUrl())
            ->line(__('base-tenant::invites.email_line2'));
    }
}
