<?php

namespace Features\Webhooks\CreateWebhook\Application;

use App\Models\Organization;

class CreateWebhook
{
    public function __construct(
        public string $name,
        public string $url,
        public array $events,
        public ?string $secret,
        public bool $enabled,
        public array $headers,
        public int $timeout,
        public int $retryLimit,
        public Organization $organization
    ) {}
}
