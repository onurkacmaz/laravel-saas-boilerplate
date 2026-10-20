<?php

namespace Features\Webhooks\UpdateWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Shared\Support\WebhookRules;
use Illuminate\Support\Str;
use Infrastructure\Tenant\TenantContext;

class UpdateWebhookHandler
{
    public function __invoke(UpdateWebhook $command): Webhook
    {
        /** @var Webhook $webhook */
        $webhook = TenantContext::getOrganization()->webhooks()->findOrFail($command->webhookId);

        $data = $command->data;

        if (array_key_exists('headers', $data)) {
            $data['headers'] = WebhookRules::sanitizeHeaders($data['headers']);
        }

        if (array_key_exists('events', $data)) {
            $data['events'] = array_values(array_unique($data['events']));
        }

        // Webhooks are always signed: clearing the secret rotates it instead.
        if (array_key_exists('secret', $data) && empty($data['secret'])) {
            $data['secret'] = Str::random(48);
        }

        $webhook->update($data);

        return $webhook->fresh();
    }
}
