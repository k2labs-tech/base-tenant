<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Base\Tenant\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The personal data export is ready to download.
 *
 * A time-limited link rather than the archive attached: a file full of
 * somebody's personal data should not sit in a mailbox forever, and mail
 * providers strip large attachments anyway.
 */
class PersonalDataReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * How long the link lives. Long enough to notice the email, short enough
     * that a forwarded message stops working.
     */
    public const MINUTES = 60 * 24;

    public function __construct(protected File $file) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('base-tenant::gdpr.ready_subject'))
            ->line(__('base-tenant::gdpr.ready_line'))
            ->action(__('base-tenant::gdpr.ready_action'), $this->file->url(self::MINUTES))
            ->line(__('base-tenant::gdpr.ready_expiry', ['hours' => (int) (self::MINUTES / 60)]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('base-tenant::gdpr.ready_subject'),
            'message' => __('base-tenant::gdpr.ready_line'),
            'action_url' => $this->file->url(self::MINUTES),
            'action_text' => __('base-tenant::gdpr.ready_action'),
            'icon' => 'arrow-down-tray',
            'priority' => 'medium',
            'category' => 'gdpr',
        ];
    }
}
