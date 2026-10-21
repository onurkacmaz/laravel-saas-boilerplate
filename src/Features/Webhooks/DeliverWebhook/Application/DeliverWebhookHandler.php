<?php

namespace Features\Webhooks\DeliverWebhook\Application;

use Features\Webhooks\Infrastructure\Eloquent\Models\WebhookDelivery;
use Features\Webhooks\Shared\Support\WebhookRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Infrastructure\Security\Support\UrlGuard;
use Throwable;

class DeliverWebhookHandler
{
    /**
     * Send one delivery attempt and record its outcome.
     *
     * Only a 2xx answer counts as a success. The request body is signed with
     * HMAC-SHA256 over "{timestamp}.{body}" using the webhook secret; the
     * timestamp is sent in X-Webhook-Timestamp so receivers can reject replays.
     */
    public function __invoke(DeliverWebhook $command): WebhookDelivery
    {
        $webhook = $command->webhook;

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::query()->create([
            'webhook_id' => $webhook->id,
            'event_name' => $command->eventName,
            'payload' => $command->payload,
            'status' => 'pending',
            'attempt' => max(1, $command->attempt),
        ]);

        // Re-checked at delivery time: DNS records may have changed since the
        // URL was validated (DNS rebinding).
        if (! UrlGuard::isAllowed($webhook->url)) {
            $delivery->markAsFailed(null, 'Delivery blocked: the endpoint resolves to a private or reserved address.');

            return $delivery->fresh();
        }

        try {
            $body = json_encode($command->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $timestamp = (string) now()->getTimestamp();

            $headers = array_merge(WebhookRules::sanitizeHeaders($webhook->headers), [
                'User-Agent' => (string) config('webhooks.user_agent', 'SaaS-Webhooks/1.0'),
                'X-Webhook-Event' => $command->eventName,
                'X-Webhook-Delivery' => $delivery->id,
                'X-Webhook-Timestamp' => $timestamp,
            ]);

            if ($webhook->secret) {
                $headers['X-Webhook-Signature'] = hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret);
            }

            $response = Http::timeout(max(1, (int) $webhook->timeout))
                ->connectTimeout(min(10, max(1, (int) $webhook->timeout)))
                ->withHeaders($headers)
                ->withoutRedirecting()
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $responseBody = Str::limit((string) $response->body(), (int) config('webhooks.max_response_body', 65535), '');

            if ($response->successful()) {
                $delivery->markAsSuccess($response->status(), $responseBody);
            } else {
                $delivery->markAsFailed($response->status(), 'Endpoint responded with HTTP '.$response->status().'.', $responseBody);
            }
        } catch (Throwable $e) {
            $delivery->markAsFailed(null, Str::limit($e->getMessage(), 1000));
        }

        $webhook->updateLastTriggered();

        return $delivery->fresh();
    }
}
