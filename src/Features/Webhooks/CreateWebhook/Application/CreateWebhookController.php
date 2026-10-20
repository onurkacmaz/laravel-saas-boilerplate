<?php

namespace Features\Webhooks\CreateWebhook\Application;

use Dedoc\Scramble\Attributes\Group;
use Features\Webhooks\Shared\Resources\WebhookResource;
use Illuminate\Http\JsonResponse;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

#[Group('Webhooks')]
class CreateWebhookController
{
    /**
     * Create a webhook endpoint.
     *
     * A signing secret is generated when none is provided; it is returned in
     * the response and must be used to verify the `X-Webhook-Signature` header.
     */
    public function __invoke(CreateWebhookRequest $request, CreateWebhookHandler $handler): JsonResponse
    {
        $webhook = $handler(
            command: new CreateWebhook(
                name: (string) $request->validated('name'),
                url: (string) $request->validated('url'),
                events: array_values(array_unique($request->validated('events'))),
                secret: $request->validated('secret'),
                enabled: $request->boolean('is_active', true),
                headers: $request->validated('headers') ?? [],
                timeout: $request->integer('timeout', 30),
                retryLimit: $request->integer('retry_limit', 3),
                organization: TenantContext::getOrganization()
            )
        );

        return $webhook->toResource(WebhookResource::class)->additional([
            'status' => 'success',
            'message' => 'Webhook created successfully',
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
