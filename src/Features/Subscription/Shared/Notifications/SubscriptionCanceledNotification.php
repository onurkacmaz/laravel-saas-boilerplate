<?php

namespace Features\Subscription\Shared\Notifications;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCanceledNotification extends Notification implements ShouldQueue
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
        $endsAt = $this->subscription->ends_at?->format('F j, Y');

        $message = (new MailMessage)
            ->subject("Your {$planName} subscription has been canceled")
            ->greeting('Hello!')
            ->line("Your **{$planName}** subscription for **{$organizationName}** has been canceled.");

        if ($endsAt) {
            $message->line("You will continue to have access to all features until **{$endsAt}**.");
        } else {
            $message->line('Your access has been immediately revoked.');
        }

        $message->line('If you change your mind, you can reactivate your subscription anytime.')
            ->action('View Plans', rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/plans')
            ->line('If you have any questions, please contact our support team.')
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
            'plan_name' => $this->subscription->plan?->name,
            'organization_name' => ($this->subscription->organization?->name ?? config('app.name')),
            'ends_at' => $this->subscription->ends_at,
            'canceled_at' => $this->subscription->canceled_at,
        ];
    }
}
