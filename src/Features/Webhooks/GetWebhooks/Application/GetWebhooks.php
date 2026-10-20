<?php

namespace Features\Webhooks\GetWebhooks\Application;

use App\Models\Organization;

class GetWebhooks
{
    public function __construct(
        public Organization $organization,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {}
}
