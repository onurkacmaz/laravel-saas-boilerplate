<?php

namespace Features\Webhooks\UpdateWebhook\Application;

class UpdateWebhook
{
    public function __construct(
        public string $webhookId,
        public array $data
    ) {}
}
