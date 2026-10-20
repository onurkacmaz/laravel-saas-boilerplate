<?php

namespace Features\Webhooks\GetWebhookDeliveries\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookDeliveryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Http\Pagination;
use Infrastructure\Tenant\TenantContext;

#[Group('Webhooks')]
class GetWebhookDeliveriesController
{
    /**
     * List the deliveries of a webhook, newest first.
     *
     * Supports `status` (pending, success, failed), `page` and `per_page` (max 100).
     */
    public function __invoke(Request $request, string $webhookId, GetWebhookDeliveriesHandler $handler): JsonResource
    {
        $status = $request->query('status');

        $deliveries = $handler(
            command: new GetWebhookDeliveries(
                organization: TenantContext::getOrganization(),
                webhookId: $webhookId,
                page: Pagination::page($request),
                perPage: Pagination::perPage($request, 10),
                status: in_array($status, ['pending', 'success', 'failed'], true) ? $status : null,
            )
        );

        return $deliveries->toResourceCollection(WebhookDeliveryResource::class)->additional([
            'status' => 'success',
            'message' => 'Webhook deliveries retrieved successfully.',
        ]);
    }
}
