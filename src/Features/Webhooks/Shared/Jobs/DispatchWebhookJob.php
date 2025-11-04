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

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Webhook $webhook,
        public string $eventName,
        public array $payload
    ) {
        //
    }

    /**
     * Execute the job.
     */
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
        $handler(command: new DeliverWebhook(
            webhook: $this->webhook,
            eventName: $this->eventName,
            payload: $this->payload,
        ));
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'webhook:'.$this->webhook->id,
            'event:'.$this->eventName,
            'organization:'.$this->webhook->organization_id,
        ];
    }
}
