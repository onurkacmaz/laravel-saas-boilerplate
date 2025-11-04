<?php

namespace Features\Webhooks\GetWebhook\Application;

class GetWebhook
{
    public function __construct(
        public string $webhookId
    ) {}
}
