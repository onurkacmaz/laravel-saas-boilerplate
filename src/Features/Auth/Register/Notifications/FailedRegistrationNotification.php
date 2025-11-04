<?php

namespace Features\Auth\Register\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FailedRegistrationNotification extends Notification implements ShouldQueue
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
        $dashboardUrl = config('app.frontend_url').'/dashboard';
        $docsUrl = config('app.frontend_url').'/docs';

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').'!')
            ->greeting("Hello {$this->user->name}!")
            ->line('Welcome to **'.config('app.name').'**! We are excited to have you on board.')
            ->line('Here are a few things you can do to get started:')
            ->line('✓ Create your first organization')
            ->line('✓ Invite team members to collaborate')
            ->line('✓ Explore our features and tools')
            ->line('✓ Set up two-factor authentication for extra security')
            ->action('Go to Dashboard', $dashboardUrl)
            ->line("If you need help getting started, check out our [documentation]({$docsUrl}).")
            ->line('Have questions? Our support team is here to help!')
            ->salutation('Best regards, The '.config('app.name').' Team');
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
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
        ];
    }
}
