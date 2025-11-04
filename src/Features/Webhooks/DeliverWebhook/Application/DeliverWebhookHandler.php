<?php

namespace Features\Webhooks\DeliverWebhook\Application;

use Exception;
use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class DeliverWebhookHandler
{
    public function __invoke(DeliverWebhook $command): Model|WebhookDelivery|null
    {
        // Create a delivery record
        $delivery = WebhookDelivery::query()->create([
            'webhook_id' => $command->webhook->id,
            'event_name' => $command->eventName,
            'payload' => $command->payload,
            'status' => 'pending',
        ]);

        try {
            // Prepare headers
            $headers = array_merge(
                $command->webhook->headers ?? [],
                [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'SaaS-Webhooks/1.0',
                ]
            );

            // Add HMAC signature if secret is set
            if ($command->webhook->secret) {
                $headers['X-Webhook-Signature'] = hash_hmac('sha256', json_encode($command->payload), $command->webhook->secret);
            }

            // Send HTTP request
            $response = Http::timeout($command->webhook->timeout)
                ->withHeaders($headers)
                ->post($command->webhook->url, $command->payload);

            // Mark as successful
            $delivery->markAsSuccess($response->status(), $response->body());

            // Update webhook last triggered time
            $command->webhook->updateLastTriggered();
        } catch (Exception $e) {
            // Mark as failed
            $delivery->markAsFailed(null, $e->getMessage());
        }

        return $delivery->fresh();
    }
}
