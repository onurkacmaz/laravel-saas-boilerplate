<?php

namespace Features\Webhooks\CreateWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateWebhookHandler
{
    public function __invoke(CreateWebhook $command): Model|Webhook
    {
        if (! isset($command->secret)) {
            $command->secret = Str::random(32);
        }

        return Webhook::query()->create([
            'organization_id' => $command->organization->id,
            'name' => $command->name,
            'url' => $command->url,
            'events' => $command->events,
            'secret' => $command->secret,
            'enabled' => $command->enabled,
            'headers' => $command->headers,
            'timeout' => $command->timeout,
            'retry_limit' => $command->retryLimit,
        ]);
    }
}
