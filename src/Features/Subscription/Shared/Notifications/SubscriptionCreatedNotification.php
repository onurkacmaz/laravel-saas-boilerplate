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
        $planName = $this->subscription->plan->name;
        $organizationName = $this->subscription->organization->name;
        $dashboardUrl = config('app.frontend_url').'/organizations/'.$this->subscription->organization_id.'/dashboard';

        $message = (new MailMessage)
            ->subject("Welcome to {$planName}!")
            ->greeting('Hello!')
            ->line("Thank you for subscribing to **{$planName}** for **{$organizationName}**!");

        if ($this->subscription->onTrial() && $this->subscription->trial_ends_at) {
            $trialEndsAt = $this->subscription->trial_ends_at->format('F j, Y');
            $message->line("Your trial period is active until **{$trialEndsAt}**. Enjoy full access to all features!");
        }

        $message->line('Here are your plan benefits:')
            ->line("- **Users:** {$this->formatLimit($this->subscription->plan->limits['users'] ?? null)}")
            ->line("- **Storage:** {$this->formatLimit($this->subscription->plan->limits['storage'] ?? null, 'MB')}")
            ->line("- **API Calls:** {$this->formatLimit($this->subscription->plan->limits['api_calls'] ?? null)}")
            ->line("- **Projects:** {$this->formatLimit($this->subscription->plan->limits['projects'] ?? null)}")
            ->action('Go to Dashboard', $dashboardUrl)
            ->line('If you have any questions, our support team is here to help!')
            ->salutation('Best regards, '.config('app.name'));

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
            'plan_name' => $this->subscription->plan->name,
            'organization_name' => $this->subscription->organization->name,
            'trial_ends_at' => $this->subscription->trial_ends_at,
            'starts_at' => $this->subscription->starts_at,
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
