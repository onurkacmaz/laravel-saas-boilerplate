<?php

namespace Features\Webhooks\GetWebhookDeliveries\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookDeliveryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Tenant\TenantContext;
use Throwable;

#[Group('Webhooks')]
class GetWebhookDeliveriesController
{
    /**
     * Get webhook deliveries.
     *
     * @throws Throwable
     */
    public function __invoke(string $webhookId, GetWebhookDeliveriesHandler $handler): JsonResource
    {
        $webhooks = $handler(
            command: new GetWebhookDeliveries(
                organization: TenantContext::getOrganization(),
                webhookId: $webhookId,
                page: request()->input('page'),
                perPage: request()->input('per_page'),
            )
        );

        return $webhooks->toResourceCollection(WebhookDeliveryResource::class)->additional([
            'status' => 'success',
            'message' => 'Webhook deliveries retrieved successfully.',
        ]);
    }
}
