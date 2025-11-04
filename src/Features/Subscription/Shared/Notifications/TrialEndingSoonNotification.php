<?php

namespace Features\Subscription\Shared\Notifications;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription
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
        $trialEndsAt = $this->subscription->trial_ends_at?->format('F j, Y');
        $daysRemaining = $this->subscription->trial_ends_at?->diffInDays(now());
        $plansUrl = config('app.frontend_url').'/plans';

        $message = (new MailMessage)
            ->subject("Your {$planName} trial is ending soon")
            ->greeting('Hello!')
            ->line("Your trial for **{$planName}** on **{$organizationName}** is ending soon.");

        if ($trialEndsAt) {
            $message->line("Your trial will end on **{$trialEndsAt}** ({$daysRemaining} days remaining).");
        }

        $message->line('To continue enjoying all the features without interruption, please add a payment method.')
            ->action('Update Billing', $plansUrl)
            ->line("After your trial ends, you'll still have access to the free plan features.")
            ->line('Questions? Our support team is here to help!')
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
            'days_remaining' => $this->subscription->trial_ends_at?->diffInDays(now()),
        ];
    }
}
