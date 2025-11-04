<?php

namespace Features\Webhooks\DeleteWebhook\Application;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

#[Group('Webhooks')]
class DeleteWebhookController
{
    /**
     * Delete a webhook.
     */
    public function __invoke(string $webhookId, DeleteWebhookHandler $handler): Response
    {
        $handler(
            command: new DeleteWebhook(
                webhookId: $webhookId
            )
        );

        return response()->noContent();
    }
}
