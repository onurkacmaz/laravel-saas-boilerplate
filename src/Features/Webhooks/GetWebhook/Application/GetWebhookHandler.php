<?php

namespace Features\Webhooks\GetWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;

class GetWebhookHandler
{
    public function __invoke(GetWebhook $command): Webhook
    {
        return Webhook::query()->findOrFail($command->webhookId);
    }
}
