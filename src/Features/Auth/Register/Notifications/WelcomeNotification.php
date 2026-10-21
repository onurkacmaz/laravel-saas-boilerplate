<?php

namespace Features\Auth\Register\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
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
        $dashboardUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/dashboard';
        $docsUrl = config('app.docs_url');
        $app = config('app.name');

        $message = (new MailMessage)
            ->subject(__('Welcome to :app!', ['app' => $app]))
            ->greeting(__('Hello :name!', ['name' => $this->user->name]))
            ->line(__('Welcome to **:app**! We are excited to have you on board.', ['app' => $app]))
            ->line(__('Here are a few things you can do to get started:'))
            ->line('✓ '.__('Create your first organization'))
            ->line('✓ '.__('Invite team members to collaborate'))
            ->line('✓ '.__('Explore our features and tools'))
            ->line('✓ '.__('Set up two-factor authentication for extra security'))
            ->action(__('Go to Dashboard'), $dashboardUrl);

        // Only applications that publish documentation link to it.
        if (is_string($docsUrl) && $docsUrl !== '') {
            $message->line(__('If you need help getting started, check out our [documentation](:url).', ['url' => $docsUrl]));
        }

        return $message
            ->line(__('Have questions? Our support team is here to help!'))
            ->salutation(__('Best regards, The :app Team', ['app' => $app]));
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
