<?php

namespace Features\Webhooks\UpdateWebhook\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Group('Webhooks')]
class UpdateWebhookController
{
    /**
     * Update a webhook.
     */
    public function __invoke(UpdateWebhookRequest $request, string $webhookId, UpdateWebhookHandler $handler): JsonResponse
    {
        $webhook = $handler(
            command: new UpdateWebhook(
                webhookId: $webhookId,
                data: $request->validated()
            )
        );

        return $webhook->toResource(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => __('Webhook updated successfully'),
        ])->response()->setStatusCode(Response::HTTP_OK);
    }
}
