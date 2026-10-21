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
        $planName = ($this->subscription->plan?->name ?? config('app.name'));
        $organizationName = ($this->subscription->organization?->name ?? config('app.name'));
        $trialEndsAt = $this->subscription->trial_ends_at?->isoFormat('LL');
        $daysRemaining = $this->daysRemaining();
        $plansUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/plans';

        $message = (new MailMessage)
            ->subject(__('Your :plan trial is ending soon', ['plan' => $planName]))
            ->greeting(__('Hello!'))
            ->line(__('Your trial for **:plan** on **:organization** is ending soon.', ['plan' => $planName, 'organization' => $organizationName]));

        if ($trialEndsAt) {
            $message->line(trans_choice('Your trial will end on **:date** (:count day remaining).|Your trial will end on **:date** (:count days remaining).', (int) $daysRemaining, ['date' => $trialEndsAt]));
        }

        $message->line(__('To continue enjoying all the features without interruption, please add a payment method.'))
            ->action(__('Update Billing'), $plansUrl)
            ->line(__("After your trial ends, you'll still have access to the free plan features."))
            ->line(__('Questions? Our support team is here to help!'))
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
            'days_remaining' => $this->daysRemaining(),
        ];
    }

    /**
     * Calendar days left in the trial. Counting days rather than rounding the
     * elapsed time keeps the number stable whatever the time of day the
     * notification is sent (a trial ending in three days says 3, not 4).
     */
    private function daysRemaining(): ?int
    {
        $trialEndsAt = $this->subscription->trial_ends_at;

        if (! $trialEndsAt) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($trialEndsAt->copy()->startOfDay(), false));
    }
}
