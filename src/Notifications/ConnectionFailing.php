<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Base\Tenant\Models\AccountConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A connection stopped working.
 *
 * Sent on the transition into failing and not on every check, so the message
 * still means something the third time it arrives.
 */
class ConnectionFailing extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Named `accountConnection` and not `connection`: `Queueable` already
     * defines `$connection` for the queue connection, and two properties of
     * that name in one class is a fatal composition error.
     */
    public function __construct(
        protected AccountConnection $accountConnection,
        protected ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('base-tenant::connections.notification.subject', [
                'provider' => $this->accountConnection->provider,
            ]))
            ->line(__('base-tenant::connections.notification.line', [
                'provider' => $this->accountConnection->provider,
                'label' => $this->accountConnection->label,
            ]));

        if ($this->reason) {
            $message->line($this->reason);
        }

        if ($url = $this->url()) {
            $message->action(__('base-tenant::connections.notification.action'), $url);
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('base-tenant::connections.notification.subject', [
                'provider' => $this->accountConnection->provider,
            ]),
            'message' => $this->reason ?? __('base-tenant::connections.notification.line', [
                'provider' => $this->accountConnection->provider,
                'label' => $this->accountConnection->label,
            ]),
            'action_url' => $this->url(),
            'action_text' => __('base-tenant::connections.notification.action'),
            'icon' => 'link-slash',
            'priority' => 'high',
            'category' => 'connections',
            'provider' => $this->accountConnection->provider,
        ];
    }

    protected function url(): ?string
    {
        return app('router')->has('base-tenant.connections.index')
            ? route('base-tenant.connections.index')
            : null;
    }
}
