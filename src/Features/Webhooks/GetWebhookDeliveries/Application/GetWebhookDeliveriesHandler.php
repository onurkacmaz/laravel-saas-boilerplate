<?php

namespace Features\Webhooks\GetWebhookDeliveries\Application;

use Illuminate\Pagination\LengthAwarePaginator;

class GetWebhookDeliveriesHandler
{
    public function __invoke(GetWebhookDeliveries $command): LengthAwarePaginator
    {
        $webhook = $command->organization->webhooks()->findOrFail($command->webhookId);

        return $webhook->deliveries()
            ->when($command->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(
                perPage: $command->perPage,
                page: $command->page
            );
    }
}
