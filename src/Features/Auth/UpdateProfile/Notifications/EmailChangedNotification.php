<?php

namespace Features\Auth\UpdateProfile\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the previous address when an account's e-mail address changes, so
 * the owner notices a change they did not make.
 */
class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $newEmail,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your e-mail address was changed'))
            ->greeting(__('Hello :name!', ['name' => $this->name]))
            ->line(__('The e-mail address of your account was changed to :email.', ['email' => $this->newEmail]))
            ->line(__('From now on, sign in and receive e-mails with the new address.'))
            ->line(__('If you did not make this change, reset your password and contact our support team right away.'))
            ->salutation(__('Best regards, :app', ['app' => config('app.name')]));
    }
}
