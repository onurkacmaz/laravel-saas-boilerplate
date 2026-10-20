<?php

namespace Features\Auth\Register\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FailedRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $name,
        public string $reason,
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
        return (new MailMessage)
            ->subject('['.config('app.name').'] A user registration failed')
            ->greeting('Hello!')
            ->line('A new user tried to register but the registration could not be completed.')
            ->line('**E-mail:** '.($this->email !== '' ? $this->email : 'n/a'))
            ->line('**Name:** '.($this->name !== '' ? $this->name : 'n/a'))
            ->line('**Reason:** '.Str::limit($this->reason, 500))
            ->line('Please check the application logs for more details.')
            ->salutation('— '.config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'reason' => $this->reason,
        ];
    }
}
