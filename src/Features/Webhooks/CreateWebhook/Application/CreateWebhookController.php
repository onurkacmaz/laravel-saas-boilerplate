<?php

namespace Features\Webhooks\CreateWebhook\Application;

use App\Models\Organization;
use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\JsonResponse;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

#[Group('Webhooks')]
class CreateWebhookController
{
    /**
     * Create a new webhook.
     */
    public function __invoke(CreateWebhookRequest $request, Organization $organization, CreateWebhookHandler $handler): JsonResponse
    {
        $webhook = $handler(
            command: new CreateWebhook(
                name: $request->input('name'),
                url: $request->input('url'),
                events: $request->input('events'),
                secret: $request->input('secret'),
                enabled: $request->input('enabled', true),
                headers: $request->input('headers', []),
                timeout: $request->input('timeout', 30),
                retryLimit: $request->input('retry_limit', 3),
                organization: TenantContext::getOrganization()
            )
        );

        return $webhook->toResource(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => 'Webhook created successfully',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
