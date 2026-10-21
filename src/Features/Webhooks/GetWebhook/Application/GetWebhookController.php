<?php

namespace Features\Webhooks\GetWebhook\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\Resources\Json\JsonResource;

#[Group('Webhooks')]
class GetWebhookController
{
    /**
     * Get a webhook.
     */
    public function __invoke(string $webhookId, GetWebhookHandler $handler): JsonResource
    {
        $webhook = $handler(
            command: new GetWebhook(
                webhookId: $webhookId
            )
        );

        return $webhook->toResource(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => __('Webhook fetched successfully'),
        ]);
    }
}
