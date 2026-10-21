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
        $endsAt = $this->subscription->ends_at?->isoFormat('LL');

        $message = (new MailMessage)
            ->subject(__('Your :plan subscription has been canceled', ['plan' => $planName]))
            ->greeting(__('Hello!'))
            ->line(__('Your **:plan** subscription for **:organization** has been canceled.', ['plan' => $planName, 'organization' => $organizationName]));

        if ($endsAt) {
            $message->line(__('You will continue to have access to all features until **:date**.', ['date' => $endsAt]));
        } else {
            $message->line(__('Your access has been immediately revoked.'));
        }

        $message->line(__('If you change your mind, you can reactivate your subscription anytime.'))
            ->action(__('View Plans'), rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/plans')
            ->line(__('If you have any questions, please contact our support team.'))
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
            'ends_at' => $this->subscription->ends_at,
            'canceled_at' => $this->subscription->canceled_at,
        ];
    }
}
