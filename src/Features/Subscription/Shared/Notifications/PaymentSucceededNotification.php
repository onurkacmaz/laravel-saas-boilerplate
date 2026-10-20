<?php

namespace Features\Subscription\Shared\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public array $paymentData
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format(((int) ($this->paymentData['amount_paid'] ?? $this->paymentData['amount_received'] ?? 0)) / 100, 2);
        $currency = strtoupper($this->paymentData['currency'] ?? 'usd');

        return (new MailMessage)
            ->subject('Payment Successful')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your payment has been processed successfully.')
            ->line("Amount: {$amount} {$currency}")
            ->line("Organization: {$this->organization->name}")
            ->line('Thank you for your business!')
            ->action('View Dashboard', rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/dashboard')
            ->line('If you have any questions, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'amount' => $this->paymentData['amount_paid'] ?? 0,
            'currency' => $this->paymentData['currency'] ?? 'usd',
            'payment_id' => $this->paymentData['id'] ?? null,
        ];
    }
}
