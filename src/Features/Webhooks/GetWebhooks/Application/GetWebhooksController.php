<?php

namespace Features\Webhooks\GetWebhooks\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Infrastructure\Http\Pagination;
use Infrastructure\Tenant\TenantContext;

#[Group('Webhooks')]
class GetWebhooksController
{
    /**
     * List the organization's webhooks.
     *
     * Supports `search` (name or URL), `page` and `per_page` (max 100).
     */
    public function __invoke(Request $request, GetWebhooksHandler $handler): JsonResource
    {
        $search = $request->query('search');

        $webhooks = $handler(
            command: new GetWebhooks(
                organization: TenantContext::getOrganization(),
                search: is_string($search) ? $search : null,
                page: Pagination::page($request),
                perPage: Pagination::perPage($request, 10),
            )
        );

        return $webhooks->toResourceCollection(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => __('Webhook fetched successfully'),
        ]);
    }
}
