<?php

namespace Features\Auth\TwoFactorAuth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorDisabledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Authenticatable $user
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
        $securityUrl = config('app.frontend_url').'/settings/security';

        return (new MailMessage)
            ->subject(__('Two-Factor Authentication Disabled'))
            ->greeting(__('Hello :name!', ['name' => $this->user->name]))
            ->line(__('Two-factor authentication has been disabled on your account.'))
            ->line(__('Your account is now protected by your password only.'))
            ->line(__('**We recommend keeping two-factor authentication enabled** for better security.'))
            ->action(__('Re-enable Two-Factor Authentication'), $securityUrl)
            ->line(__('If you did not disable two-factor authentication, please contact our support team immediately and change your password.'))
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
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ];
    }
}
