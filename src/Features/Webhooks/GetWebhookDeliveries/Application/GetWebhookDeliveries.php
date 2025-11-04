<?php

namespace Features\Webhooks\GetWebhookDeliveries\Application;

use App\Models\Organization;

class GetWebhookDeliveries
{
    public function __construct(
        public Organization $organization,
        public string $webhookId,
        public ?int $page = 1,
        public ?int $perPage = 10
    ) {}
}
