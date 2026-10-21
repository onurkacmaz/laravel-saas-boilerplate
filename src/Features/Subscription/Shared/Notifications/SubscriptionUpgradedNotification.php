<?php

namespace Features\Subscription\Shared\Notifications;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public Plan $oldPlan
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
        $newPlanName = ($this->subscription->plan?->name ?? config('app.name'));
        $oldPlanName = $this->oldPlan->name;
        $organizationName = ($this->subscription->organization?->name ?? config('app.name'));
        $dashboardUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/organizations/'.$this->subscription->organization_id.'/dashboard';
        $limits = $this->subscription->plan?->limits ?? [];

        return (new MailMessage)
            ->subject(__("You've upgraded to :plan!", ['plan' => $newPlanName]))
            ->greeting(__('Hello!'))
            ->line(__("Great news! You've successfully upgraded **:organization** from **:old** to **:new**.", ['organization' => $organizationName, 'old' => $oldPlanName, 'new' => $newPlanName]))
            ->line(__('Your new plan includes:'))
            ->line('- **'.__('Users').':** '.$this->formatLimit($limits['users'] ?? null))
            ->line('- **'.__('Storage').':** '.$this->formatLimit($limits['storage'] ?? null, 'MB'))
            ->line('- **'.__('API Calls').':** '.$this->formatLimit($limits['api_calls'] ?? null))
            ->line('- **'.__('Projects').':** '.$this->formatLimit($limits['projects'] ?? null))
            ->line(__('All features are immediately available!'))
            ->action(__('Go to Dashboard'), $dashboardUrl)
            ->line(__('Thank you for choosing a higher plan!'))
            ->salutation(__('Best regards, :app', ['app' => config('app.name')]));
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
            'new_plan_name' => $this->subscription->plan?->name,
            'old_plan_name' => $this->oldPlan->name,
            'organization_name' => ($this->subscription->organization?->name ?? config('app.name')),
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
