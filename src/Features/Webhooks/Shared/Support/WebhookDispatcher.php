<?php

namespace Features\Webhooks\Shared\Support;

use App\Models\Organization;
use Features\Webhooks\Infrastructure\Eloquent\Models\Webhook;
use Features\Webhooks\Shared\Jobs\DispatchWebhookJob;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * Queue a delivery of $event to every active webhook of the organization
     * that subscribed to it. Returns the number of queued deliveries.
     */
    public function dispatch(Organization|string|null $organization, string $event, array $data = []): int
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (! $organizationId) {
            return 0;
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'organization_id' => $organizationId,
            'data' => $data,
        ];

        $queued = 0;

        Webhook::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->each(function (Webhook $webhook) use ($event, $payload, &$queued) {
                if (! $webhook->isSubscribedTo($event)) {
                    return;
                }

                // Only send once the surrounding transaction (if any) committed,
                // so receivers never hear about changes that were rolled back.
                DispatchWebhookJob::dispatch($webhook, $event, $payload)->afterCommit();
                $queued++;
            });

        return $queued;
    }
}
