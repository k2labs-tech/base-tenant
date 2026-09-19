<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The sign-in link itself.
 *
 * Mail only, never stored in the database notification list: it holds a live
 * credential, and the bell is a place a shoulder-surfer can read.
 */
class MagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $token,
        protected string $email,
        protected int $ttlMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('base-tenant.magic-link.show', ['token' => $this->token]);

        return (new MailMessage)
            ->subject(__('base-tenant::passwordless.mail.subject', ['app' => config('app.name')]))
            ->greeting(__('base-tenant::passwordless.mail.greeting'))
            ->line(__('base-tenant::passwordless.mail.line'))
            ->action(__('base-tenant::passwordless.mail.action'), $url)
            ->line(__('base-tenant::passwordless.mail.expiry', ['minutes' => $this->ttlMinutes]))
            ->line(__('base-tenant::passwordless.mail.ignore'));
    }
}
