<?php

namespace Features\Webhooks\Shared\Jobs;

use Features\Webhooks\DeliverWebhook\Application\DeliverWebhook;
use Features\Webhooks\DeliverWebhook\Application\DeliverWebhookHandler;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Failed deliveries are retried by releasing the job back onto the queue
     * (see handle()); the job itself must never be retried on exceptions, or a
     * delivery could be sent twice.
     */
    public int $tries = 1;

    /**
     * The webhook may have been deleted before the job runs.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Webhook $webhook,
        public string $eventName,
        public array $payload,
        public int $attempt = 1,
    ) {
        //
    }

    public function handle(): void
    {
        // Check if the webhook is still active
        if (! $this->webhook->is_active) {
            return;
        }

        // Check if a webhook is subscribed to this event
        if (! $this->webhook->isSubscribedTo($this->eventName)) {
            return;
        }

        $handler = new DeliverWebhookHandler;
        $delivery = $handler(command: new DeliverWebhook(
            webhook: $this->webhook,
            eventName: $this->eventName,
            payload: $this->payload,
            attempt: $this->attempt,
        ));

        // retry_limit is the maximum number of attempts for one event.
        if ($delivery->isFailed() && $this->attempt < (int) $this->webhook->retry_limit) {
            $backoff = (array) config('webhooks.backoff', [60]);
            $delay = (int) ($backoff[$this->attempt - 1] ?? end($backoff) ?: 60);

            static::dispatch($this->webhook, $this->eventName, $this->payload, $this->attempt + 1)
                ->delay(now()->addSeconds($delay));
        }
    }

    public function tags(): array
    {
        return [
            'webhook:'.$this->webhook->id,
            'event:'.$this->eventName,
            'organization:'.$this->webhook->organization_id,
        ];
    }
}
