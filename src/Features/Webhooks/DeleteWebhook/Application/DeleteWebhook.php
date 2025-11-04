<?php

namespace Features\Webhooks\DeleteWebhook\Application;

class DeleteWebhook
{
    public function __construct(
        public string $webhookId
    ) {}
}
