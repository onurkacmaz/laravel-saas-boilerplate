<?php

namespace Features\Webhooks\DeleteWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;

class DeleteWebhookHandler
{
    public function __invoke(DeleteWebhook $command): void
    {
        $webhook = Webhook::query()->findOrFail($command->webhookId);
        $webhook->delete();
    }
}
