<?php

namespace Features\Webhooks\DeliverWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;

class DeliverWebhook
{
    public function __construct(
        public Webhook $webhook,
        public string $eventName,
        public array $payload,
        public int $attempt = 1,
    ) {}
}
