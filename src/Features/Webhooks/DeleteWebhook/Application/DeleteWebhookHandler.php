<?php

namespace Features\Webhooks\DeleteWebhook\Application;

use Infrastructure\Tenant\TenantContext;

class DeleteWebhookHandler
{
    public function __invoke(DeleteWebhook $command): void
    {
        TenantContext::getOrganization()->webhooks()->findOrFail($command->webhookId)->delete();
    }
}
