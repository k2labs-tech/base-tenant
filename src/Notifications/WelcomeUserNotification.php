<?php

namespace Base\Tenant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $password;

    public string $createdBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $password, string $createdBy)
    {
        $this->password = $password;
        $this->createdBy = $createdBy;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('base-tenant.login');

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->createdBy.' has created an account for you on '.config('app.name').'.')
            ->line('Here are your login credentials:')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Temporary Password:** '.$this->password)
            ->action('Login Now', $loginUrl)
            ->line('**Important:** For security reasons, you will be required to change your password upon first login.')
            ->line('If you have any questions, please contact your administrator.')
            ->salutation('Best regards, '.config('app.name').' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'password' => $this->password,
            'created_by' => $this->createdBy,
        ];
    }
}
