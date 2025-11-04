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
        $newPlanName = $this->subscription->plan->name;
        $oldPlanName = $this->oldPlan->name;
        $organizationName = $this->subscription->organization->name;
        $dashboardUrl = config('app.frontend_url').'/organizations/'.$this->subscription->organization_id.'/dashboard';

        return (new MailMessage)
            ->subject("You've upgraded to {$newPlanName}!")
            ->greeting('Hello!')
            ->line("Great news! You've successfully upgraded **{$organizationName}** from **{$oldPlanName}** to **{$newPlanName}**.")
            ->line('Your new plan includes:')
            ->line("- **Users:** {$this->formatLimit($this->subscription->plan->limits['users'] ?? null)}")
            ->line("- **Storage:** {$this->formatLimit($this->subscription->plan->limits['storage'] ?? null, 'MB')}")
            ->line("- **API Calls:** {$this->formatLimit($this->subscription->plan->limits['api_calls'] ?? null)}")
            ->line("- **Projects:** {$this->formatLimit($this->subscription->plan->limits['projects'] ?? null)}")
            ->line('All features are immediately available!')
            ->action('Go to Dashboard', $dashboardUrl)
            ->line('Thank you for choosing a higher plan!')
            ->salutation('Best regards, '.config('app.name'));
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
            'new_plan_name' => $this->subscription->plan->name,
            'old_plan_name' => $this->oldPlan->name,
            'organization_name' => $this->subscription->organization->name,
        ];
    }

    private function formatLimit(?int $value, string $unit = ''): string
    {
        if ($value === null || $value === -1) {
            return 'Unlimited';
        }

        return number_format($value).($unit ? ' '.$unit : '');
    }
}
