<?php

namespace Features\Subscription\Shared\Notifications;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription|\Laravel\Cashier\Subscription $subscription
    ) {}

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
        $planName = ($this->subscription->plan?->name ?? config('app.name'));
        $organizationName = ($this->subscription->organization?->name ?? config('app.name'));
        $dashboardUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/organizations/'.$this->subscription->organization_id.'/dashboard';
        $limits = $this->subscription->plan?->limits ?? [];

        $message = (new MailMessage)
            ->subject(__('Welcome to :plan!', ['plan' => $planName]))
            ->greeting(__('Hello!'))
            ->line(__('Thank you for subscribing to **:plan** for **:organization**!', ['plan' => $planName, 'organization' => $organizationName]));

        if ($this->subscription->onTrial() && $this->subscription->trial_ends_at) {
            $message->line(__('Your trial period is active until **:date**. Enjoy full access to all features!', ['date' => $this->subscription->trial_ends_at->isoFormat('LL')]));
        }

        $message->line(__('Here are your plan benefits:'))
            ->line('- **'.__('Users').':** '.$this->formatLimit($limits['users'] ?? null))
            ->line('- **'.__('Storage').':** '.$this->formatLimit($limits['storage'] ?? null, 'MB'))
            ->line('- **'.__('API Calls').':** '.$this->formatLimit($limits['api_calls'] ?? null))
            ->line('- **'.__('Projects').':** '.$this->formatLimit($limits['projects'] ?? null))
            ->action(__('Go to Dashboard'), $dashboardUrl)
            ->line(__('If you have any questions, our support team is here to help!'))
            ->salutation(__('Best regards, :app', ['app' => config('app.name')]));

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'organization_name' => ($this->subscription->organization?->name ?? config('app.name')),
            'trial_ends_at' => $this->subscription->trial_ends_at,
            'starts_at' => $this->subscription->starts_at,
        ];
    }

    private function formatLimit(?int $value, string $unit = ''): string
    {
        if ($value === null || $value === -1) {
            return __('Unlimited');
        }

        return number_format($value).($unit ? ' '.$unit : '');
    }
}
