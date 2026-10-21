<?php

namespace Features\Subscription\Shared\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
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
        $amount = number_format(((int) ($this->paymentData['amount_due'] ?? $this->paymentData['amount'] ?? 0)) / 100, 2);
        $currency = strtoupper($this->paymentData['currency'] ?? 'usd');

        return (new MailMessage)
            ->subject(__('Payment Failed'))
            ->error()
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We encountered an issue processing your payment.'))
            ->line(__('Amount: :amount', ['amount' => "{$amount} {$currency}"]))
            ->line(__('Organization: :organization', ['organization' => $this->organization->name]))
            ->line(__('Please update your payment method to avoid service interruption.'))
            ->action(__('Update Payment Method'), rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/').'/settings/billing')
            ->line(__('If you need assistance, please contact our support team.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'organization_name' => $this->organization->name,
            'amount' => $this->paymentData['amount_due'] ?? 0,
            'currency' => $this->paymentData['currency'] ?? 'usd',
            'payment_id' => $this->paymentData['id'] ?? null,
            'failure_message' => $this->paymentData['last_payment_error']['message'] ?? 'Unknown error',
        ];
    }
}
