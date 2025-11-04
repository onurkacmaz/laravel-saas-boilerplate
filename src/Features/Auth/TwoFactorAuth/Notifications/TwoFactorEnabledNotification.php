<?php

namespace Features\Auth\TwoFactorAuth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorEnabledNotification extends Notification implements ShouldQueue
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
            ->subject('Two-Factor Authentication Enabled')
            ->greeting("Hello {$this->user->name}!")
            ->line('Two-factor authentication has been successfully enabled on your account.')
            ->line('This adds an extra layer of security by requiring both your password and an authentication code to sign in.')
            ->line('**What this means:**')
            ->line('- You will need to enter a code from your authenticator app each time you log in')
            ->line('- Your recovery codes can be used if you lose access to your authenticator app')
            ->line('- Keep your recovery codes in a safe place')
            ->action('Manage Security Settings', $securityUrl)
            ->line('If you did not enable two-factor authentication, please contact our support team immediately.')
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
            'enabled_at' => now(),
        ];
    }
}
