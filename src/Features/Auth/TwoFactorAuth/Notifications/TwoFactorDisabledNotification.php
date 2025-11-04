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
            ->subject('Two-Factor Authentication Disabled')
            ->greeting("Hello {$this->user->name}!")
            ->line('Two-factor authentication has been disabled on your account.')
            ->line('Your account is now protected by your password only.')
            ->line('**We recommend keeping two-factor authentication enabled** for better security.')
            ->action('Re-enable Two-Factor Authentication', $securityUrl)
            ->line('If you did not disable two-factor authentication, please contact our support team immediately and change your password.')
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
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ];
    }
}
