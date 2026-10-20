<?php

namespace Features\Webhooks\CreateWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Shared\Support\WebhookRules;
use Illuminate\Support\Str;

class CreateWebhookHandler
{
    public function __invoke(CreateWebhook $command): Webhook
    {
        /** @var Webhook $webhook */
        $webhook = $command->organization->webhooks()->create([
            'name' => $command->name,
            'url' => $command->url,
            'events' => $command->events,
            'secret' => $command->secret ?: Str::random(48),
            'is_active' => $command->enabled,
            'headers' => WebhookRules::sanitizeHeaders($command->headers),
            'timeout' => $command->timeout,
            'retry_limit' => $command->retryLimit,
        ]);

        return $webhook;
    }
}
