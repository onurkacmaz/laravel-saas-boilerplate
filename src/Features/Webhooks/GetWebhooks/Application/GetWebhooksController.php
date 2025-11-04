<?php

namespace Features\Webhooks\GetWebhooks\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Tenant\TenantContext;
use Throwable;

#[Group('Webhooks')]
class GetWebhooksController
{
    /**
     * Get all webhooks.
     *
     * @throws Throwable
     */
    public function __invoke(GetWebhooksHandler $handler): JsonResource
    {
        $webhooks = $handler(
            command: new GetWebhooks(
                organization: TenantContext::getOrganization(),
                search: request()->input('search'),
                page: request()->input('page'),
                perPage: request()->input('per_page'),
            )
        );

        return $webhooks->toResourceCollection(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => 'Webhook fetched successfully',
        ]);
    }
}
