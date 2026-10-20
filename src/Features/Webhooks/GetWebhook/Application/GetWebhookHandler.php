<?php

namespace Features\Webhooks\GetWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Infrastructure\Tenant\TenantContext;

class GetWebhookHandler
{
    public function __invoke(GetWebhook $command): Webhook
    {
        /** @var Webhook */
        return TenantContext::getOrganization()->webhooks()->findOrFail($command->webhookId);
    }
}
