<?php

declare(strict_types=1);

namespace Base\Tenant\Notifications;

use Base\Tenant\Metering\Metric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The account has crossed a usage level worth telling them about.
 *
 * Sent once per level per period. The 80% warning is the useful one -- it
 * arrives while there is still time to do something; the 100% one exists
 * because work is being refused by then and silence would read as a fault.
 */
class UsageThresholdReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Metric $metric,
        protected int $threshold,
        protected int $value,
        protected int $limit,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->threshold >= 100 ? 'reached' : 'approaching';

        $message = (new MailMessage)
            ->subject(__("base-tenant::metering.notification.{$key}_subject", [
                'metric' => $this->label(),
                'app' => config('app.name'),
            ]))
            ->line(__("base-tenant::metering.notification.{$key}_line", [
                'metric' => $this->label(),
                'value' => number_format($this->value),
                'limit' => number_format($this->limit),
                'percentage' => $this->threshold,
            ]));

        if ($url = $this->usageUrl()) {
            $message->action(__('base-tenant::metering.notification.action'), $url);
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('base-tenant::metering.notification.database_title', [
                'metric' => $this->label(),
            ]),
            'message' => __('base-tenant::metering.notification.database_message', [
                'value' => number_format($this->value),
                'limit' => number_format($this->limit),
                'percentage' => $this->threshold,
            ]),
            'action_url' => $this->usageUrl(),
            'action_text' => __('base-tenant::metering.notification.action'),
            'icon' => 'chart-bar',
            'priority' => $this->threshold >= 100 ? 'high' : 'medium',
            'category' => 'quota.warnings',
            'metric' => $this->metric->key,
            'threshold' => $this->threshold,
        ];
    }

    protected function label(): string
    {
        return $this->metric->label
            ? __($this->metric->label)
            : __('base-tenant::metering.metrics.'.$this->metric->key);
    }

    protected function usageUrl(): ?string
    {
        return app('router')->has('base-tenant.usage')
            ? route('base-tenant.usage')
            : null;
    }
}
