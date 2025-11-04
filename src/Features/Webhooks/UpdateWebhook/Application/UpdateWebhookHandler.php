<?php

namespace Features\Webhooks\UpdateWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Illuminate\Database\Eloquent\Model;

class UpdateWebhookHandler
{
    public function __invoke(UpdateWebhook $command): Model|Webhook
    {
        $webhook = Webhook::query()->findOrFail($command->webhookId);

        return tap($webhook)->update(
            attributes: $command->data
        )->fresh();
    }
}
